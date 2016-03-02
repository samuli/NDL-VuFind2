<?php
/**
 * Console service for sending due date reminders.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013-2016.
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
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use VuFind\Crypt\HMAC;

/**
 * Console service for sending due date reminders.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class DueDateReminders extends AbstractService
{
    const DUE_DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * ILS connection.
     *
     * @var \Finna\ILS\Connection
     */
    protected $catalog = null;

    /**
     * Main configuration
     *
     * @var \Zend\Config\Config
     */
    protected $mainConfig = null;

    /**
     * Datasource configuration
     *
     * @var \Zend\Config\Config
     */
    protected $datasourceConfig = null;

    /**
     * Table for user accounts
     *
     * @var \VuFind\Config
     */
    protected $configReader = null;

    /**
     * Due date reminders table
     *
     * @var \Finna\Db\Table\DueDateReminder
     */
    protected $dueDateReminderTable = null;

    /**
     * User account table
     *
     * @var \Finna\Db\Table\User
     */
    protected $userTable = null;

    /**
     * Mailer
     *
     * @var \VuFind\Mailer
     */
    protected $mailer = null;

    protected $translator = null;
    protected $recordLoader = null;

    protected $urlHelper = null;
    protected $hmac = null;

    protected $fromEmail = null;
    protected $renderer = null;

    /**
     * Constructor
     *
     * @param VuFind\Db\Table $table MetaLibSearch table.
     */
    public function __construct(
        $userTable, $dueDateReminderTable, $catalog,
        $configReader, $mailer, $renderer, $recordLoader, $hmac
    ) {
        $this->userTable = $userTable;
        $this->dueDateReminderTable = $dueDateReminderTable;
        $this->catalog = $catalog;
        $this->mainConfig = $configReader->get('config');
        $this->datasourceConfig = $configReader->get('datasources');
        $this->configReader = $configReader;
        $this->mailer = $mailer;
        $this->renderer = $renderer;
        $this->translator = $renderer->plugin('translate');
        $this->urlHelper = $renderer->plugin('url');
        $this->recordLoader = $recordLoader;
        $this->hmac = $hmac;
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
        $this->msg('Sending due date reminders');

        $users = $this->userTable->getUsersWithDueDateReminders();
        $this->msg('Processing ' . count($users) . ' users');

        foreach ($users as $user) {
            $remindLoans = $this->getReminders($user);
            if ($remindCnt = count($remindLoans)) {
                $this->msg(
                    $remindCnt . ' new loans to remind for user ' . $user->id
                );
                $this->sendReminder($user, $remindLoans);
            } else {
                $this->msg('No loans to remind for user ' . $user->id);
            }
        }

        return true;
    }

    /**
     * Process user.
     *
     * @param \Finna\Db\Table\Row\User $user User.
     *
     * @return boolean success
     */
    protected function getReminders($user)
    {
        if (!$user->email || trim($user->email) == '') {
            $this->err(
                'User ' . $user->username . ' does not have an email address, bypassing due date reminders'
            );
            return false;
        }

        $remindLoans = [];
        foreach ($user->getLibraryCards() as $card) {
            $patron = null;
            try {
                $patron = $this->catalog->patronLogin(
                    $card['cat_username'], $card['cat_password']
                );
            } catch (\Exception $e) {
                $this->err('Catalog login error: ' . $e->getMessage());
            }
        
            if (!$patron) {
                $this->err(
                    'Catalog login failed for user ' . $user->id
                    . ', account ' . $card->id . ' (' . $card->cat_username . ')'
                );
                continue;
            }
            $todayTime = new \DateTime();
            $loans = $this->catalog->getMyTransactions($patron);
            foreach ($loans as $loan) {
                $dueDate = new \DateTime($loan['duedate']);
                if ($todayTime >= $dueDate 
                    || $dueDate->diff($todayTime)->days <= $user->finna_due_date_reminder
                ) {
                    $params = [
                       'user_id' => $user->id,
                       'loan_id' => $loan['item_id'],
                       'due_date' 
                          => $dueDate->format(DueDateReminder::DUE_DATE_FORMAT)
                    ];

                    $reminder = $this->dueDateReminderTable->select($params);
                    if (count($reminder)) {
                        // Reminder already sent
                        continue;
                    }

                    // Store also title for display in email
                    $title = isset($loan['title']) 
                        ? $loan['title'] : $this->translator->translate('Title not available');

                    if (isset($loan['id'])) {
                        $record = $this->recordLoader->load($loan['id'], 'Solr');
                        if ($record && isset($record['title'])) {
                            $title = $record['title'];
                        }
                    }

                    $dateFormat = isset($this->mainConfig->Site->displayDateFormat)
                        ? $this->mainConfig->Site->displayDateFormat
                        : 'm-d-Y';
                              
                    $remindLoans[] = [
                        'loanId' => $loan['item_id'],
                        'dueDate' => $loan['duedate'],
                        'dueDateFormatted' => $dueDate->format($dateFormat),
                        'title' => $title
                    ];
                }
            }
        }
        return $remindLoans;
    }
    
    protected function sendReminder($user, $remindLoans)
    {
        $key = $this->getSecret($user, $user->id);
        $params = [
            'id' => $user->id, 
            'type' => 'reminder', 
            'key' => $key
        ];
        $unsubscribeUrl 
            = $this->url('myresearch-unsubscribe') . '?' . http_build_query($params);

        $params = [
             'loans' => $remindLoans,
             'url' => $this->url('myresearch-checkedout'),
             'unsubscribeUrl' => $unsubscribeUrl
        ];
        $subject = $this->translator->translate('due_date_email_subject');
        $message = $this->renderer->render('Email/due-date-reminder.phtml', $params);

        try {
            $to = 'samuli.sillanpaa@helsinki.fi'; //$user->email
            $this->mailer->send(
                $to, $this->fromEmail, $subject, $message
            );
            foreach ($remindLoans as $loan) {
                $params = ['user_id' => $user->id, 'load_id' => $load['id']];

                $this->dueDateReminderTable->delete($params);

                $params['due_date'] = new \DateTime($loan['duedate']);
                $params['notification_date'] 
                    = gmdate(DueDateReminder::DUE_DATE_FORMAT, time());

                $this->dueDateReminderTable->insert($params);
            }
        } catch (\Exception $e) {
            $this->err(
                'Failed to send due date reminders (user id ' 
                . $user->id . ', cat_username: ' . $user->cat_username
            );
            return false;
        }

        return true;
    }

    /**
     * Utility function for generating a token.
     *
     * @param object $user User object
     * @param string $id   ID
     *
     * @return string token
      */
    protected function getSecret($user, $id)
    {
        $data = [
           'id' => $id,
           'user_id' => $user->id,
           'created' => $user->created
        ];
        return $this->hmac->generate(array_keys($data), $data);
    }

    /**
     * Get usage information.
     *
     * @return string
     */
    protected function usage()
    {
// @codingStandardsIgnoreStart
        return <<<EOT
Usage:
  php index.php util due_date_reminders <from_email>

  Sends due date reminders.
    from_email            Sender email address.
EOT;
// @codingStandardsIgnoreEnd
    }
}
