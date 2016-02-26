<?php
/**
 * Console service for processing unregistered online payments.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;
use Finna\Db\Table\Transaction,
    Zend\Db\Sql\Select;

/**
 * Console service for processing unregistered online payments.
 *
 * @category VuFind2
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OnlinePaymentMonitor extends AbstractService
{
    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $catalog = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $mainConfig = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $datasourceConfig = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $configReader = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $transactionTable = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $userTable = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $mailer = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $viewManager = null;


    protected $expireHours;
    protected $fromEmail;
    protected $reportIntervalHours;


    /**
     * Constructor
     *
     * @param VuFind\Db\Table $table User table.
     */
    public function __construct(
        $catalog, $transactionTable, $userTable, $configReader, $mailer, $viewManager
    ) {
        $this->catalog = $catalog;
        $this->mainConfig = $configReader->get('config');
        $this->datasourceConfig = $configReader->get('datasources');
        $this->configReader = $configReader;
        $this->transactionTable = $transactionTable;
        $this->userTable = $userTable;
        $this->mailer = $mailer;
        $this->viewManager = $viewManager;
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        if (count($arguments) < 3) {
            echo $this->usage();
            return false;
        }

        $this->collectScriptArguments($arguments);
        $this->msg("OnlinePayment monitor started");

        $expiredCnt = $failedCnt = $registeredCnt = $remindCnt = 0;
        $report = [];
        $user = false;
        $failed = $this->transactionTable->getFailedTransactions();
        foreach ($failed as $t) {
            $this->processTransaction(
                $t, $report, $registeredCnt, $expiredCnt, $failedCnt, $user
            );
        }

        // Report paid and unregistered transactions whose registration
        // can not be re-tried:
        $unresolved 
            = $this->transactionTable->getUnresolvedTransactions($this->reportIntervalHours);
        foreach ($unresolved as $t) {
            $this->processUnresolvedTransaction($t, $report, $remindCnt);
        }

        if ($registeredCnt) {
            $this->msg("  Total registered: $registeredCnt");
        }
        if ($expiredCnt) {
            $this->msg("  Total expired: $expiredCnt");
        }
        if ($failedCnt) {
            $this->msg("  Total failed: $failedCnt");
        }
        if ($remindCnt) {
            $this->msg("  Total to be reminded: $remindCnt");
        }

        $this->sendReports($report);

        $this->msg("OnlinePayment monitor completed");

        return true;
    }

    /**
     * TODO
     *
     * @param array $arguments Command line arguments.
     *
     * @return void
     */
    protected function processTransaction(
        $t, &$report, &$registeredCnt, &$expiredCnt, &$failedCnt, $user
    ) {
        $this->msg("  Registering transaction id {$t->id} / {$t->transaction_id}");

        // Check if the transaction has not been registered for too long
        $now = new \DateTime();
        $paid_time = new \DateTime($t->paid);
        $diff = $now->diff($paid_time);
        $diffHours = ($diff->days*24) + $diff->h;
        if ($diffHours > $this->expireHours) {
            if (!isset($report[$t->driver])) {
                $report[$t->driver] = 0;
            }
            $report[$t->driver]++;
            $expiredCnt++;

            if (!$this->transactionTable->setTransactionReported($t->transaction_id)) {
                $this->err('    Failed to update transaction ' . $t->transaction_id . 'as reported');
            }

            $t->complete = Transaction::STATUS_REGISTRATION_EXPIRED;
            if (!$t->save()) {
                $this->err('    Failed to update transaction ' . $t->transaction_id . 'as expired.');
            } else {
                $this->msg('    Transaction ' . $t->transaction_id . ' expired.');
                return true;
            }
        } else {
            if ($user === false || $t->user_id != $user->id) {
                $user = $this->userTable->getById($t->user_id);
            }

            $catUsername = $patron = null;
            foreach ($user->getLibraryCards() as $card) {
                if ($card['cat_username'] == $t->cat_username) {
                    $patron = $this->catalog->patronLogin(
                        $t->cat_username, $card['cat_password']
                    );
                    if (!$patron) {
                        $this->err(
                            '    Could not perform patron login for transaction ' 
                            . $t->transaction_id
                        );
                        $failedCnt++;
                        return;
                    }
                }
            }
            
            try {
                $this->catalog->markFeesAsPaid($patron, $t->amount);
                if (!$this->transactionTable->setTransactionRegistered($t->transaction_id)) {
                    $this->err('    Failed to update transaction ' . $t->transaction_id . 'as registered');
                }
                $registeredCnt++;
                return true;
            } catch (\Exception $e) {
                $this->err('    Registration of transaction ' . $t->transaction_id . ' failed');
                $this->err("      " .  $e->getMessage());

                if ($this->transactionTable->setTransactionRegistrationFailed(
                    $t->transaction_id, $e->getMessage()
                )) {
                    $this->err(
                        "Error updating transaction $transactionId status: "
                        . 'registering failed'
                    );                    
                }
                $failedCnt++;
                return false;
            }
        }
    }

    /**
     * TODO
     *
     * @param array $arguments Command line arguments.
     *
     * @return void
     */
    protected function processUnresolvedTransaction($t, &$report, &$remindCnt)
    {
        $this->msg("  Transaction id {$t->transaction_id} still unresolved.");
        
        if (!$this->transactionTable->setTransactionReported($t->transaction_id)) {
            $this->err(
                '    Failed to update transaction ' 
                . $t->transaction_id . ' as reported'
            );
        }
        if (!isset($report[$t->driver])) {
            $report[$t->driver] = 0;
        }
        $report[$t->driver]++;
        $remindCnt++;
    }

    /**
     * TODO
     *
     * @param array $arguments Command line arguments.
     *
     * @return void
     */
    protected function sendReports($report)
    {
        $renderer = $this->viewManager->getRenderer();

        $subject
            = 'Finna: ilmoitus tietokannan %s epäonnistuneista verkkomaksuista';

        // Send report of transactions that need to be resolved manually:
        foreach ($report as $driver => $cnt) {
            if ($cnt) {
                $settings = $this->configReader->get("VoyagerRestful_$driver");
                if (!$settings || !isset($settings['OnlinePayment']['errorEmail'])) {
                    $this->err(
                        "  No error email for expired transactions not defined for "
                        . "driver $driver ($cnt expired transactions)"
                    );
                    continue;
                }

                $email = $settings['OnlinePayment']['errorEmail'];
                $this->msg(
                    "  [$driver] Inform $cnt expired transactions "
                    . "for driver $driver to $email"
                );

                $params = [
                   'driver' => $driver,
                   'cnt' => $cnt
                ];
                $messageSubject = sprintf($subject, $driver);

                $message 
                    = $renderer->render('Email/online-payment-alert.phtml', $params);

                try {
                    $this->mailer->send(
                        $email, $this->fromEmail, $messageSubject, $message
                    );
                } catch (\Exception $e) {
                    $this->err("    Failed to send error email to customer: $email");
                    continue;
                }
            }
        }
    }

    /**
     * Collect script parameters and print usage
     * information when all required parameters were not given.
     *
     * @param array $argv Arguments
     *
     * @return void
     */
    protected function collectScriptArguments($arguments)
    {
        $this->expireHours = $arguments[0];
        $this->fromEmail = $arguments[1];
        $this->reportIntervalHours = $arguments[2];
    }

    /**
     * Get script usage information.
     *
     * @return string
     */
    protected function usage()
    {
        return <<<EOT
Usage:
  php index.php util online_payment_monitor <expire_hours> <internal_error_email> <from_email> <report_interval_hours> 

  Validates unregistered online payment transactions.
    expire_hours          Number of hours before considering unregistered
                          transaction to be expired.
    from_email            Sender email address for notification of expired transactions
    report_interval_hours Interval when to re-send report of unresolved transactions
EOT;
    }
}




