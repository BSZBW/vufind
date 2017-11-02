<?php
return [
    'extends' => false,
    'helpers' => [
        'factories' => [
            'accountCapabilities' => 'VuFind\View\Helper\Root\Factory::getAccountCapabilities',
            'addThis' => 'VuFind\View\Helper\Root\Factory::getAddThis',
            'alphabrowse' => 'VuFind\View\Helper\Root\Factory::getAlphaBrowse',
            'auth' => 'VuFind\View\Helper\Root\Factory::getAuth',
            'authorNotes' => 'VuFind\View\Helper\Root\Factory::getAuthorNotes',
            'cart' => 'VuFind\View\Helper\Root\Factory::getCart',
            'citation' => 'VuFind\View\Helper\Root\Factory::getCitation',
            'dateTime' => 'VuFind\View\Helper\Root\Factory::getDateTime',
            'displayLanguageOption' => 'VuFind\View\Helper\Root\Factory::getDisplayLanguageOption',
            'export' => 'VuFind\View\Helper\Root\Factory::getExport',
            'feedback' => 'VuFind\View\Helper\Root\Factory::getFeedback',
            'flashmessages' => 'VuFind\View\Helper\Root\Factory::getFlashmessages',
            'geocoords' => 'VuFind\View\Helper\Root\Factory::getGeoCoords',
            'googleanalytics' => 'VuFind\View\Helper\Root\Factory::getGoogleAnalytics',
            'helpText' => 'VuFind\View\Helper\Root\Factory::getHelpText',
            'historylabel' => 'VuFind\View\Helper\Root\Factory::getHistoryLabel',
            'ils' => 'VuFind\View\Helper\Root\Factory::getIls',
            'jsTranslations' => 'VuFind\View\Helper\Root\Factory::getJsTranslations',
            'keepAlive' => 'VuFind\View\Helper\Root\Factory::getKeepAlive',
            'permission' => 'VuFind\View\Helper\Root\Factory::getPermission',
            'proxyUrl' => 'VuFind\View\Helper\Root\Factory::getProxyUrl',
            'openUrl' => 'VuFind\View\Helper\Root\Factory::getOpenUrl',
            'piwik' => 'VuFind\View\Helper\Root\Factory::getPiwik',
            'recaptcha' => 'VuFind\View\Helper\Root\Factory::getRecaptcha',
            'record' => 'VuFind\View\Helper\Root\Factory::getRecord',
            'recordDataFormatter' => 'VuFind\View\Helper\Root\RecordDataFormatterFactory',
            'recordLink' => 'VuFind\View\Helper\Root\Factory::getRecordLink',
            'related' => 'VuFind\View\Helper\Root\Factory::getRelated',
            'safeMoneyFormat' => 'VuFind\View\Helper\Root\Factory::getSafeMoneyFormat',
            'searchbox' => 'VuFind\View\Helper\Root\Factory::getSearchBox',
            'searchMemory' => 'VuFind\View\Helper\Root\Factory::getSearchMemory',
            'searchOptions' => 'VuFind\View\Helper\Root\Factory::getSearchOptions',
            'searchParams' => 'VuFind\View\Helper\Root\Factory::getSearchParams',
            'searchTabs' => 'VuFind\View\Helper\Root\Factory::getSearchTabs',
            'summaries' => 'VuFind\View\Helper\Root\Factory::getSummaries',
            'syndeticsPlus' => 'VuFind\View\Helper\Root\Factory::getSyndeticsPlus',
            'systemEmail' => 'VuFind\View\Helper\Root\Factory::getSystemEmail',
            'userlist' => 'VuFind\View\Helper\Root\Factory::getUserList',
            'usertags' => 'VuFind\View\Helper\Root\Factory::getUserTags',
        ],
        'invokables' => [
            'addellipsis' => 'VuFind\View\Helper\Root\AddEllipsis',
            'browse' => 'VuFind\View\Helper\Root\Browse',
            'context' => 'VuFind\View\Helper\Root\Context',
            'currentPath' => 'VuFind\View\Helper\Root\CurrentPath',
            'highlight' => 'VuFind\View\Helper\Root\Highlight',
            'jqueryvalidation' => 'VuFind\View\Helper\Root\JqueryValidation',
            'localizedNumber' => 'VuFind\View\Helper\Root\LocalizedNumber',
            'printms' => 'VuFind\View\Helper\Root\Printms',
            'recommend' => 'VuFind\View\Helper\Root\Recommend',
            'renderArray' => 'VuFind\View\Helper\Root\RenderArray',
            'resultfeed' => 'VuFind\View\Helper\Root\ResultFeed',
            'sortFacetList' => 'VuFind\View\Helper\Root\SortFacetList',
            'summon' => 'VuFind\View\Helper\Root\Summon',
            'transEsc' => 'VuFind\View\Helper\Root\TransEsc',
            'translate' => 'VuFind\View\Helper\Root\Translate',
            'truncate' => 'VuFind\View\Helper\Root\Truncate',
        ]
    ],
];
