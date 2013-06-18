<?php
return array(
    'di' => array(
        'instance' => array(
            'alias' => array(
                // services
                'msssendza_caster_service' => 'MssSendza\Service\Caster',
                'msssendza_sendza_service' => 'MssSendza\Service\Sendza',
                'msssendza_sender_service' => 'MssSendza\Service\Sender',
            ),
            'msssendza_sender_service' => array(
                'parameters' => array(
                    'em' => 'doctrine_em'
                )
            ),
            'Zend\View\PhpRenderer' => array(
                'parameters' => array(
                    'resolver' => 'Zend\View\TemplatePathStack',
                    'options'  => array(
                        'script_paths' => array(
                            'msssendza' => __DIR__ . '/../views'
                        ),
                    ),
                ),
            ),
        ),
    ),
    'routes' => array(
    ),
);
