<?php
namespace VuFindApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindApi\Controller\ApiController' => 'VuFindApi\Controller\Factory::getApiController',
            'VuFindApi\Controller\SearchApiController' => 'VuFindApi\Controller\Factory::getSearchApiController',
        ],
        'aliases' => [
            'Api' => 'VuFindApi\Controller\ApiController',
            'SearchApi' => 'VuFindApi\Controller\SearchApiController',
        ],
    ],
    'router' => [
        'routes' => [
            'apiHome' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api[/v1][/]',
                    'defaults' => [
                        'controller' => 'Api',
                        'action'     => 'Index',
                    ]
                ],
            ],
            'searchApiv1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/search',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'search',
                    ]
                ]
            ],
            'recordApiv1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/record',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'record',
                    ]
                ]
            ]
        ],
    ],
];

return $config;
