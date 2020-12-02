<?php
/**
 * Finna Module Configuration
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2020.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://github.com/KDK-Alli/NDL-VuFind2   NDL-VuFind2
 */
namespace Finna\Module\Configuration;

$config = [
    'router' => [
        'routes' => [
            'comments-inappropriate' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/Comments/Inappropriate/[:id]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Comments',
                        'action'     => 'Inappropriate',
                    ]
                ]
            ],
            'feed-content-page' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/FeedContent[/:page][/:element]',
                    'constraints' => [
                        'page'     => '[a-zA-Z][a-zA-Z0-9_-]*'
                    ],
                    'defaults' => [
                        'controller' => 'FeedContent',
                        'action'     => 'Content',
                    ]
                ],
            ],
            'linked-events-content' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/FeedContent/LinkedEvents[/:id]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'FeedContent',
                        'action'     => 'LinkedEvents',
                    ]
                ],
            ],
            'list-save' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/List/[:id]/save',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'ListPage',
                        'action'     => 'Save',
                    ]
                ]
            ],
            'list-page' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/List[/:lid]',
                    'constraints' => [
                        'lid'     => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'ListPage',
                        'action'     => 'List',
                    ]
                ],
            ],
            'myresearch-changemessagingsettings' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeMessagingSettings',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeMessagingSettings',
                    ]
                ],
            ],
            'myresearch-changeprofileaddress' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeProfileAddress',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeProfileAddress',
                    ]
                ],
            ],
            'myresearch-unsubscribe' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Unsubscribe',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Unsubscribe',
                    ]
                ],
            ],
            'myresearch-export' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Export',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Export',
                    ]
                ],
            ],
            'myresearch-import' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Import',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Import',
                    ]
                ],
            ],
            'record-feedback' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/Record/[:id]/Feedback',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Record',
                        'action'     => 'Feedback',
                    ]
                ]
            ],
            'record-preview' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/RecordPreview',
                    'defaults' => [
                        'controller' => 'Record',
                        'action'     => 'PreviewForm',
                    ]
                ],
            ],
            'solrrecord-feedback' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/Record/[:id]/Feedback',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Record',
                        'action'     => 'Feedback',
                    ]
                ]
            ],
            'solrauthrecord-feedback' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/AuthorityRecord/[:id]/Feedback',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'AuthorityRecord',
                        'action'     => 'Feedback',
                    ]
                ]
            ],
        ],
    ],
    'route_manager' => [
        'aliases' => [
            'Laminas\Mvc\Router\Http\Segment' => 'Laminas\Router\Http\Segment'
        ]
    ],
    'controllers' => [
        'factories' => [
            'Finna\Controller\AjaxController' => 'VuFind\Controller\AjaxControllerFactory',
            'Finna\Controller\AuthorityController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\AuthorityRecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\BarcodeController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\BrowseController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CartController' => 'VuFind\Controller\CartControllerFactory',
            'Finna\Controller\CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CombinedController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CommentsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ContentController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CoverController' => 'Finna\Controller\CoverControllerFactory',
            'Finna\Controller\EdsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ErrorController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ExternalAuthController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\R2FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\FeedContentController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\LibraryCardsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\LocationServiceController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MetaLibController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MetalibRecordController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MyResearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\OrganisationInfoController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PCIController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PrimoController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PrimoRecordController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\R2RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\R2CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\R2SearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\R2RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\R2CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\R2SearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ShibbolethLogoutNotificationController' => 'Finna\Controller\ShibbolethLogoutNotificationControllerFactory',
            'Finna\Controller\ListController' => 'Finna\Controller\ListControllerFactory',
            'Finna\Controller\L1Controller' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\L1recordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
        ],
        'aliases' => [
            'AuthorityRecord' => 'Finna\Controller\AuthorityRecordController',
            'Barcode' => 'Finna\Controller\BarcodeController',
            'barcode' => 'Finna\Controller\BarcodeController',
            'Comments' => 'Finna\Controller\CommentsController',
            'comments' => 'Finna\Controller\CommentsController',
            'FeedContent' => 'Finna\Controller\FeedContentController',
            'feedcontent' => 'Finna\Controller\FeedContentController',
            'LocationService' => 'Finna\Controller\LocationServiceController',
            'locationservice' => 'Finna\Controller\LocationServiceController',
            'r2collection' => 'Finna\Controller\R2CollectionController',
            'R2Collection' => 'Finna\Controller\R2CollectionController',
            'r2feedback' => 'Finna\Controller\R2FeedbackController',
            'R2Feedback' => 'Finna\Controller\R2FeedbackController',
            'VuFind\Controller\FeedbackController' => 'Finna\Controller\FeedbackController',
            'r2record' => 'Finna\Controller\R2RecordController',
            'R2Record' => 'Finna\Controller\R2RecordController',
            'R2' => 'Finna\Controller\R2SearchController',
            'MetaLib' => 'Finna\Controller\MetaLibController',
            'metalib' => 'Finna\Controller\MetaLibController',
            'MetaLibRecord' => 'Finna\Controller\MetaLibrecordController',
            'metalibrecord' => 'Finna\Controller\MetaLibrecordController',
            'OrganisationInfo' => 'Finna\Controller\OrganisationInfoController',
            'organisationinfo' => 'Finna\Controller\OrganisationInfoController',
            'r2collection' => 'Finna\Controller\R2CollectionController',
            'R2Collection' => 'Finna\Controller\R2CollectionController',
            'r2record' => 'Finna\Controller\R2RecordController',
            'R2Record' => 'Finna\Controller\R2RecordController',
            'R2' => 'Finna\Controller\R2SearchController',
            'ListPage' => 'Finna\Controller\ListController',
            'listpage' => 'Finna\Controller\ListController',
            'r2feedback' => 'Finna\Controller\R2FeedbackController',
            'R2Feedback' => 'Finna\Controller\R2FeedbackController',
            'L1' => 'Finna\Controller\L1Controller',
            'l1' => 'Finna\Controller\L1Controller',
            'L1Record' => 'Finna\Controller\L1recordController',
            'l1record' => 'Finna\Controller\L1recordController',

            // Overrides:
            'VuFind\Controller\AuthorityController' => 'Finna\Controller\AuthorityController',
            'VuFind\Controller\AjaxController' => 'Finna\Controller\AjaxController',
            'VuFind\Controller\BrowseController' => 'Finna\Controller\BrowseController',
            'VuFind\Controller\CartController' => 'Finna\Controller\CartController',
            'VuFind\Controller\CombinedController' => 'Finna\Controller\CombinedController',
            'VuFind\Controller\CollectionController' => 'Finna\Controller\CollectionController',
            'VuFind\Controller\ContentController' => 'Finna\Controller\ContentController',
            'VuFind\Controller\CoverController' => 'Finna\Controller\CoverController',
            'VuFind\Controller\EdsController' => 'Finna\Controller\EdsController',
            'VuFind\Controller\ErrorController' => 'Finna\Controller\ErrorController',
            'VuFind\Controller\ExternalAuthController' => 'Finna\Controller\ExternalAuthController',
            'VuFind\Controller\FeedbackController' => 'Finna\Controller\FeedbackController',
            'VuFind\Controller\LibraryCardsController' => 'Finna\Controller\LibraryCardsController',
            'VuFind\Controller\MyResearchController' => 'Finna\Controller\MyResearchController',
            'VuFind\Controller\PrimoController' => 'Finna\Controller\PrimoController',
            'VuFind\Controller\PrimoRecordController' => 'Finna\Controller\PrimoRecordController',
            'VuFind\Controller\RecordController' => 'Finna\Controller\RecordController',
            'VuFind\Controller\SearchController' => 'Finna\Controller\SearchController',
            'VuFind\Controller\ShibbolethLogoutNotificationController' => 'Finna\Controller\ShibbolethLogoutNotificationController',

            // Legacy:
            'PCI' => 'Finna\Controller\PrimoController',
            'pci' => 'Finna\Controller\PrimoController',
        ]
    ],
    'controller_plugins' => [
        'factories' => [
            'Finna\Controller\Plugin\Captcha' => 'Finna\Controller\Plugin\CaptchaFactory',
        ],
        'aliases' => [
            'VuFind\Controller\Plugin\Captcha' => 'Finna\Controller\Plugin\Captcha'
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'Finna\Autocomplete\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Auth\ILSAuthenticator' => 'VuFind\Auth\ILSAuthenticatorFactory',
            'Finna\Auth\Manager' => 'VuFind\Auth\ManagerFactory',
            'Finna\Cache\Manager' => 'VuFind\Cache\ManagerFactory',
            'Finna\Config\PluginManager' => 'VuFind\Config\PluginManagerFactory',
            'Finna\Config\SearchSpecsReader' => 'VuFind\Config\YamlReaderFactory',
            'Finna\Config\YamlReader' => 'VuFind\Config\YamlReaderFactory',
            'Finna\Cover\Loader' => 'VuFind\Cover\LoaderFactory',
            'Finna\Feed\Feed' => 'Finna\Feed\FeedFactory',
            'Finna\Feed\LinkedEvents' => 'Finna\Feed\LinkedEventsFactory',
            'Finna\Form\Form' => 'Finna\Form\FormFactory',
            'Finna\Form\R2Form' => 'Finna\Form\FormFactory',
            'Finna\ILS\Connection' => 'VuFind\ILS\ConnectionFactory',
            'Finna\LocationService\LocationService' => 'Finna\LocationService\LocationServiceFactory',
            'Finna\Mailer\Mailer' => 'VuFind\Mailer\Factory',
            'Finna\OAI\Server' => 'VuFind\OAI\ServerFactory',
            'Finna\OnlinePayment\OnlinePayment' => 'Finna\OnlinePayment\OnlinePaymentFactory',
            'Finna\OnlinePayment\Session' => 'Finna\OnlinePayment\OnlinePaymentSessionFactory',
            'Finna\OrganisationInfo\OrganisationInfo' => 'Finna\OrganisationInfo\OrganisationInfoFactory',
            'Finna\Record\Loader' => 'Finna\Record\LoaderFactory',
            'Finna\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\RecordTab\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\RecordTab\TabManager' => 'VuFind\RecordTab\TabManagerFactory',
            'Finna\Role\PermissionManager' => 'VuFind\Role\PermissionManagerFactory',
            'Finna\Search\Memory' => 'VuFind\Search\MemoryFactory',
            'Finna\Search\Solr\AuthorityHelper' => 'Finna\Search\Solr\AuthorityHelperFactory',
            'Finna\Search\Solr\HierarchicalFacetHelper' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'Finna\Service\R2SupportService' => 'Finna\Service\R2SupportServiceFactory',
            'Finna\Favorites\FavoritesService' => 'Finna\Favorites\FavoritesServiceFactory',
            'Finna\Service\R2SupportService' => 'Finna\Service\R2SupportServiceFactory',
            'Finna\Service\RemsService' => 'Finna\Service\RemsServiceFactory',
            'FinnaSearch\Service' => 'VuFind\Service\SearchServiceFactory',

            'VuFind\Search\SearchTabsHelper' => 'Finna\Search\SearchTabsHelperFactory',
            'VuFindHttp\HttpService' => 'Finna\Service\HttpServiceFactory',

            'Laminas\Session\SessionManager' => 'Finna\Session\ManagerFactory',
        ],
        'aliases' => [
            'VuFind\Autocomplete\PluginManager' => 'Finna\Autocomplete\PluginManager',
            'VuFind\Auth\Manager' => 'Finna\Auth\Manager',
            'VuFind\Auth\ILSAuthenticator' => 'Finna\Auth\ILSAuthenticator',
            'VuFind\Cache\Manager' => 'Finna\Cache\Manager',
            'VuFind\Config\PluginManager' => 'Finna\Config\PluginManager',
            'VuFind\Config\SearchSpecsReader' => 'Finna\Config\SearchSpecsReader',
            'VuFind\Config\YamlReader' => 'Finna\Config\YamlReader',
            'VuFind\Cover\Loader' => 'Finna\Cover\Loader',
            'VuFind\Favorites\FavoritesService' => 'Finna\Favorites\FavoritesService',
            'VuFind\Form\Form' => 'Finna\Form\Form',
            'VuFind\ILS\Connection' => 'Finna\ILS\Connection',
            'VuFind\Mailer\Mailer' => 'Finna\Mailer\Mailer',
            'VuFind\OAI\Server' => 'Finna\OAI\Server',
            'VuFind\RecordTab\PluginManager' => 'Finna\RecordTab\PluginManager',
            'VuFind\RecordTab\TabManager' => 'Finna\RecordTab\TabManager',
            'VuFind\Role\PermissionManager' => 'Finna\Role\PermissionManager',
            'VuFind\Search\Memory' => 'Finna\Search\Memory',
            'VuFind\Search\Solr\HierarchicalFacetHelper' => 'Finna\Search\Solr\HierarchicalFacetHelper',

            'VuFindSearch\Service' => 'FinnaSearch\Service',
        ]
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific framework components).
    'vufind' => [
        'plugin_managers' => [
            'ajaxhandler' => [
                'factories' => [
                    'Finna\AjaxHandler\AddToList' =>
                        'Finna\AjaxHandler\AddToListFactory',
                    'Finna\AjaxHandler\ChangePickupLocation' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\ChangeRequestStatus' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\CheckRequestsAreValid' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\CommentRecord' =>
                        'Finna\AjaxHandler\CommentRecordFactory',
                    'Finna\AjaxHandler\DeleteRecordComment' =>
                        'VuFind\AjaxHandler\DeleteRecordCommentFactory',
                    'Finna\AjaxHandler\EditList' =>
                        'Finna\AjaxHandler\EditListFactory',
                    'Finna\AjaxHandler\EditListResource' =>
                        'Finna\AjaxHandler\EditListResourceFactory',
                    'Finna\AjaxHandler\GetAccountNotifications' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\GetAuthorityInfo' =>
                        'Finna\AjaxHandler\GetAuthorityInfoFactory',
                    'Finna\AjaxHandler\GetAuthorityFullInfo' =>
                        'Finna\AjaxHandler\GetAuthorityFullInfoFactory',
                    'Finna\AjaxHandler\GetACSuggestions' =>
                        'VuFind\AjaxHandler\GetACSuggestionsFactory',
                    'Finna\AjaxHandler\GetContentFeed' =>
                        'Finna\AjaxHandler\GetContentFeedFactory',
                    'Finna\AjaxHandler\GetDateRangeVisual' =>
                        'Finna\AjaxHandler\GetDateRangeVisualFactory',
                    'Finna\AjaxHandler\GetDescription' =>
                        'Finna\AjaxHandler\GetDescriptionFactory',
                    'Finna\AjaxHandler\GetFacetData' =>
                        'Finna\AjaxHandler\GetFacetDataFactory',
                    'Finna\AjaxHandler\GetFeed' =>
                        'Finna\AjaxHandler\GetFeedFactory',
                    'Finna\AjaxHandler\GetHoldingsDetails' =>
                        'Finna\AjaxHandler\GetHoldingsDetailsFactory',
                    'Finna\AjaxHandler\GetImageInformation' =>
                        'Finna\AjaxHandler\GetImageInformationFactory',
                    'Finna\AjaxHandler\GetLinkedEvents' =>
                        'Finna\AjaxHandler\GetLinkedEventsFactory',
                    'Finna\AjaxHandler\GetItemStatuses' =>
                        'VuFind\AjaxHandler\GetItemStatusesFactory',
                    'Finna\AjaxHandler\GetOrganisationInfo' =>
                        'Finna\AjaxHandler\GetOrganisationInfoFactory',
                    'Finna\AjaxHandler\GetOrganisationPageFeed' =>
                        'Finna\AjaxHandler\GetOrganisationPageFeedFactory',
                    'Finna\AjaxHandler\GetPiwikPopularSearches' =>
                        'Finna\AjaxHandler\GetPiwikPopularSearchesFactory',
                    'Finna\AjaxHandler\GetRecordDriverRelatedRecords' =>
                        'Finna\AjaxHandler\GetRecordDriverRelatedRecordsFactory',
                    'Finna\AjaxHandler\GetRecordInfoByAuthority' =>
                        'Finna\AjaxHandler\GetRecordInfoByAuthorityFactory',
                    'Finna\AjaxHandler\GetRecordVersions' =>
                        'Finna\AjaxHandler\GetRecordVersionsFactory',
                    'Finna\AjaxHandler\GetRecordDriverRelatedRecords' =>
                        'Finna\AjaxHandler\GetRecordDriverRelatedRecordsFactory',
                    'Finna\AjaxHandler\GetRequestGroupPickupLocations' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\GetSearchTabsRecommendations' =>
                        'Finna\AjaxHandler\GetSearchTabsRecommendationsFactory',
                    'Finna\AjaxHandler\GetSideFacets' =>
                        'VuFind\AjaxHandler\GetSideFacetsFactory',
                    'Finna\AjaxHandler\GetSimilarRecords' =>
                        'Finna\AjaxHandler\GetSimilarRecordsFactory',
                    'Finna\AjaxHandler\GetUserList' =>
                        'Finna\AjaxHandler\GetUserListFactory',
                    'Finna\AjaxHandler\GetUserLists' =>
                        'Finna\AjaxHandler\GetUserListsFactory',
                    'Finna\AjaxHandler\ImportFavorites' =>
                        'Finna\AjaxHandler\ImportFavoritesFactory',
                    'Finna\AjaxHandler\OnlinePaymentNotify' =>
                        'Finna\AjaxHandler\AbstractOnlinePaymentActionFactory',
                    'Finna\AjaxHandler\RegisterOnlinePayment' =>
                        'Finna\AjaxHandler\AbstractOnlinePaymentActionFactory',
                    'Finna\AjaxHandler\SystemStatus' =>
                        'VuFind\AjaxHandler\SystemStatusFactory',
                ],
                'aliases' => [
                    'addToList' => 'Finna\AjaxHandler\AddToList',
                    'changePickupLocation' => 'Finna\AjaxHandler\ChangePickupLocation',
                    'changeRequestStatus' => 'Finna\AjaxHandler\ChangeRequestStatus',
                    'checkRequestsAreValid' => 'Finna\AjaxHandler\CheckRequestsAreValid',
                    'editList' => 'Finna\AjaxHandler\EditList',
                    'editListResource' => 'Finna\AjaxHandler\EditListResource',
                    'getAccountNotifications' => 'Finna\AjaxHandler\GetAccountNotifications',
                    'getAuthorityInfo' => 'Finna\AjaxHandler\GetAuthorityInfo',
                    'getAuthorityFullInfo' => 'Finna\AjaxHandler\GetAuthorityFullInfo',
                    'getContentFeed' => 'Finna\AjaxHandler\GetContentFeed',
                    'getDescription' => 'Finna\AjaxHandler\GetDescription',
                    'getDateRangeVisual' => 'Finna\AjaxHandler\GetDateRangeVisual',
                    'getFeed' => 'Finna\AjaxHandler\GetFeed',
                    'getHoldingsDetails' => 'Finna\AjaxHandler\GetHoldingsDetails',
                    'getImageInformation' => 'Finna\AjaxHandler\GetImageInformation',
                    'getLinkedEvents' => 'Finna\AjaxHandler\GetLinkedEvents',
                    'getOrganisationPageFeed' => 'Finna\AjaxHandler\GetOrganisationPageFeed',
                    'getMyLists' => 'Finna\AjaxHandler\GetUserLists',
                    'getOrganisationInfo' => 'Finna\AjaxHandler\GetOrganisationInfo',
                    'getPiwikPopularSearches' => 'Finna\AjaxHandler\GetPiwikPopularSearches',
                    'getRecordDriverRelatedRecords' => 'Finna\AjaxHandler\GetRecordDriverRelatedRecords',
                    'getRecordInfoByAuthority' => 'Finna\AjaxHandler\GetRecordInfoByAuthority',
                    'getRecordVersions' => 'Finna\AjaxHandler\GetRecordVersions',
                    'getRecordDriverRelatedRecords' => 'Finna\AjaxHandler\GetRecordDriverRelatedRecords',
                    'getSearchTabsRecommendations' => 'Finna\AjaxHandler\GetSearchTabsRecommendations',
                    'getSimilarRecords' => 'Finna\AjaxHandler\GetSimilarRecords',
                    'getUserList' => 'Finna\AjaxHandler\GetUserList',
                    'importFavorites' => 'Finna\AjaxHandler\ImportFavorites',
                    'onlinePaymentNotify' => 'Finna\AjaxHandler\OnlinePaymentNotify',
                    'registerOnlinePayment' => 'Finna\AjaxHandler\RegisterOnlinePayment',

                    // Overrides:
                    'VuFind\AjaxHandler\CommentRecord' => 'Finna\AjaxHandler\CommentRecord',
                    'VuFind\AjaxHandler\DeleteRecordComment' => 'Finna\AjaxHandler\DeleteRecordComment',
                    'VuFind\AjaxHandler\GetACSuggestions' => 'Finna\AjaxHandler\GetACSuggestions',
                    'VuFind\AjaxHandler\GetFacetData' => 'Finna\AjaxHandler\GetFacetData',
                    'VuFind\AjaxHandler\GetItemStatuses' => 'Finna\AjaxHandler\GetItemStatuses',
                    'VuFind\AjaxHandler\GetRequestGroupPickupLocations' => 'Finna\AjaxHandler\GetRequestGroupPickupLocations',
                    'VuFind\AjaxHandler\GetSideFacets' => 'Finna\AjaxHandler\GetSideFacets',
                    'VuFind\AjaxHandler\SystemStatus' => 'Finna\AjaxHandler\SystemStatus',
                ]
            ],
            'auth' => [
                'factories' => [
                    'Finna\Auth\ILS' => 'VuFind\Auth\ILSFactory',
                    'Finna\Auth\MultiILS' => 'VuFind\Auth\ILSFactory',
                    'Finna\Auth\Shibboleth' => 'VuFind\Auth\ShibbolethFactory',
                    'Finna\Auth\Suomifi' => 'Finna\Auth\SuomifiFactory',
                ],
                'aliases' => [
                    'VuFind\Auth\ILS' => 'Finna\Auth\ILS',
                    'VuFind\Auth\MultiILS' => 'Finna\Auth\MultiILS',
                    'VuFind\Auth\Shibboleth' => 'Finna\Auth\Shibboleth',
                    'Suomifi' => 'Finna\Auth\Suomifi'
                ]
            ],
            'autocomplete' => [
                'factories' => [
                    'Finna\Autocomplete\R2' => 'Finna\Autocomplete\SolrFactory',
                    'Finna\Autocomplete\Solr' => 'Finna\Autocomplete\SolrFactory',
                    'Finna\Autocomplete\L1' => 'Finna\Autocomplete\SolrFactory',
                ],
                'aliases' => [
                    'VuFind\Autocomplete\Solr' => 'Finna\Autocomplete\Solr',
                    'r2' => 'Finna\Autocomplete\R2'
                ]
            ],
            'db_row' => [
                'factories' => [
                    'Finna\Db\Row\CommentsInappropriate' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\CommentsRecord' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\DueDateReminder' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Fee' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Feedback' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaCache' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\PrivateUser' => 'VuFind\Db\Row\UserFactory',
                    'Finna\Db\Row\Resource' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Search' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Transaction' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\User' => 'Finna\Db\Row\UserFactory',
                    'Finna\Db\Row\UserCard' => 'Finna\Db\Row\UserCardFactory',
                    'Finna\Db\Row\UserList' => 'VuFind\Db\Row\UserListFactory'
                ],
                'aliases' => [
                    'VuFind\Db\Row\PrivateUser' => 'Finna\Db\Row\PrivateUser',
                    'VuFind\Db\Row\Resource' => 'Finna\Db\Row\Resource',
                    'VuFind\Db\Row\Search' => 'Finna\Db\Row\Search',
                    'VuFind\Db\Row\Transaction' => 'Finna\Db\Row\Transaction',
                    'VuFind\Db\Row\User' => 'Finna\Db\Row\User',
                    'VuFind\Db\Row\UserCard' => 'Finna\Db\Row\UserCard',
                    'VuFind\Db\Row\UserList' => 'Finna\Db\Row\UserList',

                    // Aliases for table classes without a row class counterpart
                    'Finna\Db\Row\Comments' => 'VuFind\Db\Row\Comments',
                    'Finna\Db\Row\Session' => 'VuFind\Db\Row\Session',
                    'Finna\Db\Row\UserResource' => 'VuFind\Db\Row\UserResource',

                    'commentsinappropriate' => 'Finna\Db\Row\CommentsInappropriate',
                    'commentsrecord' => 'Finna\Db\Row\CommentsRecord',
                    'duedatereminder' => 'Finna\Db\Row\DueDateReminder',
                    'fee' => 'Finna\Db\Row\Fee',
                    'finnacache' => 'Finna\Db\Row\FinnaCache',
                    'transaction' => 'Finna\Db\Row\Transaction',
                ]
            ],
            'db_table' => [
                'factories' => [
                    'Finna\Db\Table\Comments' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\CommentsInappropriate' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\CommentsRecord' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\DueDateReminder' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Fee' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Feedback' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaCache' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Resource' => 'VuFind\Db\Table\ResourceFactory',
                    'Finna\Db\Table\Search' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Session' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Transaction' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\User' => 'VuFind\Db\Table\UserFactory',
                    'Finna\Db\Table\UserList' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\UserResource' => 'VuFind\Db\Table\GatewayFactory',
                ],
                'aliases' => [
                    'VuFind\Db\Table\Comments' => 'Finna\Db\Table\Comments',
                    'VuFind\Db\Table\Resource' => 'Finna\Db\Table\Resource',
                    'VuFind\Db\Table\Search' => 'Finna\Db\Table\Search',
                    'VuFind\Db\Table\Session' => 'Finna\Db\Table\Session',
                    'VuFind\Db\Table\User' => 'Finna\Db\Table\User',
                    'VuFind\Db\Table\UserList' => 'Finna\Db\Table\UserList',
                    'VuFind\Db\Table\UserResource' => 'Finna\Db\Table\UserResource',

                    'commentsinappropriate' => 'Finna\Db\Table\CommentsInappropriate',
                    'commentsrecord' => 'Finna\Db\Table\CommentsRecord',
                    'duedatereminder' => 'Finna\Db\Table\DueDateReminder',
                    'fee' => 'Finna\Db\Table\Fee',
                    'feedback' => 'Finna\Db\Table\Feedback',
                    'finnacache' => 'Finna\Db\Table\FinnaCache',
                    'transaction' => 'Finna\Db\Table\Transaction',
                ]
            ],
            'ils_driver' => [
                'factories' => [
                    'Finna\ILS\Driver\Alma' => 'VuFind\ILS\Driver\AlmaFactory',
                    'Finna\ILS\Driver\AxiellWebServices' => 'Finna\ILS\Driver\AxiellWebServicesFactory',
                    'Finna\ILS\Driver\Demo' => 'VuFind\ILS\Driver\DemoFactory',
                    'Finna\ILS\Driver\KohaRest' => 'VuFind\ILS\Driver\KohaRestFactory',
                    'Finna\ILS\Driver\KohaRestSuomi' => 'Finna\ILS\Driver\KohaRestSuomiFactory',
                    'Finna\ILS\Driver\Mikromarc' => '\VuFind\ILS\Driver\DriverWithDateConverterFactory',
                    'Finna\ILS\Driver\MultiBackend' => 'Finna\ILS\Driver\MultiBackendFactory',
                    'Finna\ILS\Driver\NoILS' => 'VuFind\ILS\Driver\NoILSFactory',
                    'Finna\ILS\Driver\SierraRest' => 'VuFind\ILS\Driver\SierraRestFactory',
                    'Finna\ILS\Driver\Voyager' => '\VuFind\ILS\Driver\DriverWithDateConverterFactory',
                    'Finna\ILS\Driver\VoyagerRestful' => '\Finna\ILS\Driver\VoyagerRestfulFactory',
                ],
                'aliases' => [
                    'axiellwebservices' => 'Finna\ILS\Driver\AxiellWebServices',
                    'mikromarc' => 'Finna\ILS\Driver\Mikromarc',
                    'koharestsuomi' => 'Finna\ILS\Driver\KohaRestSuomi',

                    'VuFind\ILS\Driver\Alma' => 'Finna\ILS\Driver\Alma',
                    'VuFind\ILS\Driver\Demo' => 'Finna\ILS\Driver\Demo',
                    'VuFind\ILS\Driver\KohaRest' => 'Finna\ILS\Driver\KohaRest',
                    'VuFind\ILS\Driver\MultiBackend' => 'Finna\ILS\Driver\MultiBackend',
                    'VuFind\ILS\Driver\NoILS' => 'Finna\ILS\Driver\NoILS',
                    'VuFind\ILS\Driver\SierraRest' => 'Finna\ILS\Driver\SierraRest',
                    'VuFind\ILS\Driver\Voyager' => 'Finna\ILS\Driver\Voyager',
                    'VuFind\ILS\Driver\VoyagerRestful' => 'Finna\ILS\Driver\VoyagerRestful',
                ]
            ],
            'recommend' => [
                'factories' => [
                    'VuFind\Recommend\CollectionSideFacets' => 'Finna\Recommend\Factory::getCollectionSideFacets',
                    'VuFind\Recommend\SideFacets' => 'Finna\Recommend\Factory::getSideFacets',
                    'Finna\Recommend\AuthorityRecommend' => 'Finna\Recommend\AuthorityRecommendFactory',
                    'Finna\Recommend\FinnaSuggestions' => 'Finna\Recommend\FinnaSuggestionsFactory',
                    'Finna\Recommend\FinnaSuggestionsDeferred' => 'Finna\Recommend\FinnaSuggestionsDeferredFactory',
                    'Finna\Recommend\LearningMaterial' => 'Finna\Recommend\LearningMaterialFactory',
                    'Finna\Recommend\SideFacetsDeferred' => 'Finna\Recommend\Factory::getSideFacetsDeferred',
                ],
                'aliases' => [
                    'authorityrecommend' => 'Finna\Recommend\AuthorityRecommend',
                    'finnasuggestions' => 'Finna\Recommend\FinnaSuggestions',
                    'finnasuggestionsdeferred' => 'Finna\Recommend\FinnaSuggestionsDeferred',
                    'learningmaterial' => 'Finna\Recommend\LearningMaterial',
                    'sidefacetsdeferred' => 'Finna\Recommend\SideFacetsDeferred',
                ]
            ],
            'resolver_driver' => [
                'factories' => [
                    'Finna\Resolver\Driver\Sfx' => 'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
                    'Finna\Resolver\Driver\Alma' => 'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
                ],
                'aliases' => [
                    'VuFind\Resolver\Driver\Sfx' => 'Finna\Resolver\Driver\Sfx',
                    'VuFind\Resolver\Driver\Alma' => 'Finna\Resolver\Driver\Alma',
                ]
            ],
            'search_backend' => [
                'factories' => [
                    'R2' => 'Finna\Search\Factory\R2BackendFactory',
                    'R2Collection' => 'Finna\Search\Factory\R2BackendFactory',
                    'Primo' => 'Finna\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                    'SolrAuth' => 'Finna\Search\Factory\SolrAuthBackendFactory',
                    'Blender' => 'Finna\Search\Factory\BlenderBackendFactory',
                    'L1' => 'Finna\Search\Factory\L1BackendFactory',
                ],
            ],
            'search_facetcache' => [
                'factories' => [
                    'Finna\Search\R2\FacetCache' => 'VuFind\Search\Solr\FacetCacheFactory'
                ],
                'aliases' => [
                    'R2' => 'Finna\Search\R2\FacetCache'
                ]
            ],
            'search_options' => [
                'factories' => [
                    'Finna\Search\Blender\Options' => 'VuFind\Search\OptionsFactory',

                    'Finna\Search\Combined\Options' => 'VuFind\Search\OptionsFactory',
                    'Finna\Search\EDS\Options' => 'VuFind\Search\EDS\OptionsFactory',
                    'Finna\Search\R2\Options' => 'VuFind\Search\Options\OptionsFactory',
                    'Finna\Search\Primo\Options' => 'VuFind\Search\OptionsFactory',
                    'Finna\Search\SolrAuth\Options' => 'VuFind\Search\OptionsFactory',

                    'Finna\Search\L1\Options' => 'VuFind\Search\OptionsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Combined\Options' => 'Finna\Search\Combined\Options',
                    'VuFind\Search\EDS\Options' => 'Finna\Search\EDS\Options',
                    'VuFind\Search\Primo\Options' => 'Finna\Search\Primo\Options',
                    'VuFind\Search\SolrAuth\Options' => 'Finna\Search\SolrAuth\Options',

                    'Finna\Search\R2Collection\Options' => 'VuFind\Search\SolrCollection\Options',

                    // Counterpart for EmptySet Params:
                    'Finna\Search\EmptySet\Options' => 'VuFind\Search\EmptySet\Options',
                    'Finna\Search\MixedList\Options' => 'VuFind\Search\MixedList\Options',
                    'Blender' => 'Finna\Search\Blender\Options',
                    'R2' => 'Finna\Search\R2\Options',
                    'R2Collection' => 'VuFind\Search\SolrCollection\Options',
                    'SolrAuth' => 'Finna\Search\SolrAuth\Options',
                    'L1' => 'Finna\Search\L1\Options',
                ]
            ],
            'search_params' => [
                'factories' => [
                    'Finna\Search\Blender\Params' => 'Finna\Search\Blender\ParamsFactory',

                    'Finna\Search\Combined\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\EDS\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\EmptySet\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\Favorites\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\R2\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\R2Collection\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\MixedList\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\Solr\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\SolrAuth\Params' => 'Finna\Search\Solr\ParamsFactory',

                    'Finna\Search\L1\Params' => 'Finna\Search\Solr\ParamsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Combined\Params' => 'Finna\Search\Combined\Params',
                    'VuFind\Search\EDS\Params' => 'Finna\Search\EDS\Params',
                    'VuFind\Search\EmptySet\Params' => 'Finna\Search\EmptySet\Params',
                    'VuFind\Search\Favorites\Params' => 'Finna\Search\Favorites\Params',
                    'VuFind\Search\MixedList\Params' => 'Finna\Search\MixedList\Params',
                    'VuFind\Search\Solr\Params' => 'Finna\Search\Solr\Params',

                    'VuFind\Search\SolrAuth\Params' => 'Finna\Search\SolrAuth\Params',

                    'Blender' => 'Finna\Search\Blender\Params',
                    'R2' => 'Finna\Search\R2\Params',
                    'R2Collection' => 'Finna\Search\R2Collection\Params',
                    'SolrAuth' => 'Finna\Search\SolrAuth\Params',
                    'L1' => 'Finna\Search\L1\Params',
                ]
            ],
            'search_results' => [
                'factories' => [
                    'Finna\Search\Blender\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\Combined\Results' => 'VuFind\Search\Results\ResultsFactory',
                    'Finna\Search\Favorites\Results' => 'Finna\Search\Favorites\ResultsFactory',
                    'Finna\Search\R2\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\R2Collection\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\Primo\Results' => 'VuFind\Search\Results\ResultsFactory',
                    'Finna\Search\Solr\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\SolrAuth\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\L1\Results' => 'Finna\Search\L1\ResultsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Combined\Results' => 'Finna\Search\Combined\Results',
                    'VuFind\Search\Favorites\Results' => 'Finna\Search\Favorites\Results',
                    'VuFind\Search\Primo\Results' => 'Finna\Search\Primo\Results',
                    'VuFind\Search\Solr\Results' => 'Finna\Search\Solr\Results',
                    'VuFind\Search\SolrAuth\Results' => 'Finna\Search\SolrAuth\Results',

                    'Blender' => 'Finna\Search\Blender\Results',
                    'R2' => 'Finna\Search\R2\Results',
                    'R2Collection' => 'Finna\Search\R2Collection\Results',
                    'L1' => 'Finna\Search\L1\Results',
                ]
            ],
            'content_covers' => [
                'factories' => [
                    'Finna\Content\Covers\BTJ' => 'Finna\Content\Covers\BTJFactory',
                    'Finna\Content\Covers\CoverArtArchive' => 'Finna\Content\Covers\CoverArtArchiveFactory',
                ],
                'invokables' => [
                    'bookyfi' => 'Finna\Content\Covers\BookyFi',
                    'natlibfi' => 'Finna\Content\Covers\NatLibFi',
                ],
                'aliases' => [
                    'btj' => 'Finna\Content\Covers\BTJ',
                    'coverartarchive' => 'Finna\Content\Covers\CoverArtArchive',
                ]
            ],
            'recorddriver' => [
                'factories' => [
                    'Finna\RecordDriver\EDS' =>
                        'VuFind\RecordDriver\NameBasedConfigFactory',
                    'Finna\RecordDriver\R2Ead3' =>
                        'VuFind\RecordDriver\NameBasedConfigFactory',
                    'Finna\RecordDriver\R2Ead3Missing' =>
                        'VuFind\RecordDriver\NameBasedConfigFactory',
                    'Finna\RecordDriver\SolrDefault' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrMarc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrAuthEaccpf' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrAuthForward' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrAuthMarc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrEad' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrEad3' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrForward' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrLido' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrLrmi' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrMarc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrQdc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\Primo' =>
                        'VuFind\RecordDriver\NameBasedConfigFactory',
                ],
                'aliases' => [
                    'r2ead3' => 'Finna\RecordDriver\R2Ead3',
                    'r2ead3missing' => 'Finna\RecordDriver\R2Ead3Missing',

                    'SolrAuthEaccpf' => 'Finna\RecordDriver\SolrAuthEaccpf',
                    'SolrAuthForwardAuthority' => 'Finna\RecordDriver\SolrAuthForward',
                    'SolrAuthMarcAuthority' => 'Finna\RecordDriver\SolrAuthMarc',
                    'SolrEad' => 'Finna\RecordDriver\SolrEad',
                    'SolrEad3' => 'Finna\RecordDriver\SolrEad3',
                    'SolrForward' => 'Finna\RecordDriver\SolrForward',
                    'SolrLido' => 'Finna\RecordDriver\SolrLido',
                    'SolrLrmi' => 'Finna\RecordDriver\SolrLrmi',
                    'SolrQdc' => 'Finna\RecordDriver\SolrQdc',

                    'VuFind\RecordDriver\EDS' => 'Finna\RecordDriver\EDS',
                    'VuFind\RecordDriver\SolrAuthDefault' => 'Finna\RecordDriver\SolrAuthDefault',
                    'VuFind\RecordDriver\SolrDefault' => 'Finna\RecordDriver\SolrDefault',
                    'VuFind\RecordDriver\SolrMarc' => 'Finna\RecordDriver\SolrMarc',
                    'VuFind\RecordDriver\Primo' => 'Finna\RecordDriver\Primo',
                ],
                'delegators' => [
                    'Finna\RecordDriver\SolrMarc' => [
                        'VuFind\RecordDriver\IlsAwareDelegatorFactory'
                    ],
                ],
            ],
            'recordtab' => [
                'factories' => [
                    'Finna\RecordTab\AuthorityRecordsAuthor' => 'Finna\RecordTab\AuthorityRecordsFactory',
                    'Finna\RecordTab\AuthorityRecordsTopic' => 'Finna\RecordTab\AuthorityRecordsFactory',
                    'Finna\RecordTab\ExternalData' => 'Finna\RecordTab\Factory::getExternalData',
                    'Finna\RecordTab\Map' => 'Finna\RecordTab\Factory::getMap',
                    'Finna\RecordTab\R2CollectionList' => 'VuFind\RecordTab\CollectionListFactory',
                    'Finna\RecordTab\UserComments' => 'Finna\RecordTab\Factory::getUserComments',
                    'Finna\RecordTab\Versions' => 'Finna\RecordTab\VersionsFactory',
                ],
                'invokables' => [
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                ],
                'aliases' => [
                    'authorityrecordsauthor' => 'Finna\RecordTab\AuthorityRecordsAuthor',
                    'authorityrecordstopic' => 'Finna\RecordTab\AuthorityRecordsTopic',
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                    'externaldata' => 'Finna\RecordTab\ExternalData',
                    'r2collectionlist' => 'Finna\RecordTab\R2CollectionList',
                    'versions' => 'Finna\RecordTab\Versions',

                    // Overrides:
                    'VuFind\RecordTab\Map' => 'Finna\RecordTab\Map',
                    'VuFind\RecordTab\UserComments' => 'Finna\RecordTab\UserComments',
                ]
            ],
            'related' => [
                'factories' => [
                    'Finna\Related\RecordDriverRelated' => 'Finna\Related\RecordDriverRelatedFactory',
                    'Finna\Related\RecordDriverRelated' => 'Finna\Related\RecordDriverRelatedFactory',
                    'Finna\Related\Nothing' => 'Laminas\ServiceManager\Factory\InvokableFactory',
                    'Finna\Related\SimilarDeferred' => 'Laminas\ServiceManager\Factory\InvokableFactory',
                    'Finna\Related\WorkExpressions' => 'Finna\Related\WorkExpressionsFactory',
                ],
                'aliases' =>  [
                    'nothing' => 'Finna\Related\Nothing',
                    'recorddriverrelated' => 'Finna\Related\RecordDriverRelated',
                    'similardeferred' => 'Finna\Related\SimilarDeferred',
                    'workexpressions' => 'Finna\Related\WorkExpressions',
                ]
            ],
            'hierarchy_driver' => [
                'factories' => [
                    'Finna\Hierarchy\Driver\HierarchyR2' => 'VuFind\Hierarchy\Driver\ConfigurationBasedFactory'
                ],
                'aliases' => [
                    'R2' => 'Finna\Hierarchy\Driver\HierarchyR2'
                ]
            ],
            'hierarchy_treedatasource' => [
                'aliases' => [
                    'R2' => 'Finna\Hierarchy\TreeDataSource\R2'
                ],
                'factories' => [
                    'Finna\Hierarchy\TreeDataSource\R2' => 'Finna\Hierarchy\TreeDataSource\R2Factory'
                ]
            ]
        ],
    ],

    // Authorization configuration:
    'lmc_rbac' => [
        'vufind_permission_provider_manager' => [
            'factories' => [
                'Finna\Role\PermissionProvider\AuthenticationStrategy' => 'Finna\Role\PermissionProvider\AuthenticationStrategyFactory',
                'Finna\Role\PermissionProvider\IpRange' => 'VuFind\Role\PermissionProvider\IpRangeFactory'
            ],
            'aliases' => [
                'authenticationStrategy' => 'Finna\Role\PermissionProvider\AuthenticationStrategy',

                'VuFind\Role\PermissionProvider\IpRange' => 'Finna\Role\PermissionProvider\IpRange',
            ]
        ],
    ],

];

$recordRoutes = [
    'metalibrecord' => 'MetaLibRecord',
    'solrauthrecord' => 'AuthorityRecord',
    'r2record' => 'R2Record',
    'r2collection' => 'R2Collection',
    'r2collectionrecord' => 'R2Record',
    'l1record' => 'L1Record'
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
    'Comments' => ['inappropriate' => 'inappropriate/[:id]'],
    'LibraryCards' => ['newLibraryCardPassword' => 'newPassword/[:id]'],
    'MyResearch' => ['sortList' => 'SortList/[:id]'],
    'R2Feedback' => ['r2feedback-form' => 'Form/[:id]']
];

$staticRoutes = [
    'Browse/Database', 'Browse/Journal', 'Cover/Download',
    'LibraryCards/Recover', 'LibraryCards/Register',
    'LibraryCards/RegistrationDone', 'LibraryCards/RegistrationForm',
    'LibraryCards/ResetPassword',
    'LocationService/Modal',
    'R2/Home', 'R2/Results', 'R2/Advanced',
    'MetaLib/Home', 'MetaLib/Search', 'MetaLib/Advanced',
    'MyResearch/R2AccessRights',
    'MyResearch/SaveCustomOrder', 'MyResearch/PurgeHistoricLoans',
    'MyResearch/R2AccessRights',
    'OrganisationInfo/Home',
    'PCI/Home', 'PCI/Search', 'PCI/Record',
    'R2/Home', 'R2/Results', 'R2/Advanced',
    'Search/StreetSearch',
    'Barcode/Show', 'Search/MapFacet', 'Search/Blended',
    'L1/Advanced', 'L1/FacetList', 'L1/Home', 'L1/Results'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

// This needs to be defined after VuFind's record routes...
$config['router']['routes']['l1record-feedback'] = [
    'type'    => 'Laminas\Router\Http\Segment',
    'options' => [
        'route'    => '/L1Record/[:id]/Feedback',
        'constraints' => [
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'L1Record',
            'action'     => 'Feedback',
        ]
    ]
];
$config['router']['routes']['r2record-feedback'] = [
    'type'    => 'Zend\Router\Http\Segment',
    'options' => [
        'route'    => '/R2Record/[:id]/Feedback',
        'constraints' => [
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'R2Record',
            'action'     => 'Feedback',
        ]
    ]
];

$config['router']['routes']['r2record-feedback'] = [
    'type'    => 'Zend\Router\Http\Segment',
    'options' => [
        'route'    => '/R2Record/[:id]/Feedback',
        'constraints' => [
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'R2Record',
            'action'     => 'Feedback',
        ]
    ]
];

return $config;
