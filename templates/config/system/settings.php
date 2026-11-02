<?php

return [
    'BE' => [
        'debug' => false,
        'installToolPassword' => '{{installToolPassword}}',
        'passwordHashing' => [
            'className' => 'TYPO3\CMS\Core\Crypto\PasswordHashing\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'charset' => 'utf8mb4',
                'dbname' => (string)getenv('MYSQL_DATABASE'),
                'driver' => 'pdo_mysql',
                'host' => (string)getenv('MYSQL_HOST'),
                'password' => (string)getenv('MYSQL_PASSWORD'),
                'port' => 3306,
                'defaultTableOptions' => [
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
                'user' => (string)getenv('MYSQL_USER'),
            ],
        ],
    ],
    'EXTCONF' => [
        'lang' => [
            'availableLanguages' => {{availableLanguages}},
        ],
    ],
    'EXTENSIONS' => [
        'backend' => [
            'backendFavicon' => '',
            'backendLogo' => '',
            'loginBackgroundImage' => '',
            'loginFootnote' => '',
            'loginHighlightColor' => '',
            'loginLogo' => '',
            'loginLogoAlt' => '',
        ],
        'extensionmanager' => [
            'automaticInstallation' => '1',
            'offlineMode' => '0',
        ],
    ],
    'FE' => [
        'cacheHash' => [
            'enforceValidation' => true,
        ],
        'debug' => false,
        'disableNoCacheParameter' => true,
        'passwordHashing' => [
            'className' => 'TYPO3\CMS\Core\Crypto\PasswordHashing\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'GFX' => [
        'processor' => 'GraphicsMagick',
        'processor_allowTemporaryMasksAsPng' => false,
        'processor_colorspace' => 'RGB',
        'processor_effects' => false,
        'processor_enabled' => true,
        'processor_path' => '/usr/bin/',
    ],
    'LOG' => [
        'TYPO3' => [
            'CMS' => [
                'deprecations' => [
                    'writerConfiguration' => [
                        'notice' => [
                            'TYPO3\CMS\Core\Log\Writer\FileWriter' => [
                                'disabled' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'MAIL' => [
        'defaultMailFromAddress' => 'noreply@{{hostname}}',
        'transport' => 'smtp',
        'transport_sendmail_command' => '/usr/sbin/sendmail -t -i ',
        'transport_smtp_encrypt' => 'ssl',
        'transport_smtp_password' => getenv('TYPO3_SMTP_PASSWORD'),
        'transport_smtp_server' => getenv('TYPO3_SMTP_SERVER') ?: 'email-smtp.eu-west-1.amazonaws.com:465',
        'transport_smtp_username' => getenv('TYPO3_SMTP_USERNAME'),
    ],
    'SYS' => [
        'UTF8filesystem' => true,
        'caching' => [
            'cacheConfigurations' => [
                'hash' => [
                    'backend' => 'TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend',
                ],
                'imagesizes' => [
                    'backend' => 'TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
                'pages' => [
                    'backend' => 'TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
                'rootline' => [
                    'backend' => 'TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
            ],
        ],
        'devIPmask' => '',
        'displayErrors' => (string)getenv('TYPO3_CONTEXT') === 'Development' ? 1 : 0,
        'encryptionKey' => '{{encryptionKey}}',
        'exceptionalErrors' => 4096,
        'features' => [
            'security.backend.enforceContentSecurityPolicy' => true,
            'security.usePasswordPolicyForFrontendUsers' => true,
            'security.frontend.enforceContentSecurityPolicy' => true,
            'security.frontend.reportContentSecurityPolicy' => true,
        ],
        'sitename' => '{{projectName}}',
        'systemMaintainers' => [
            1,
        ],
        'reverseProxyIP' => (string)getenv('REVERSE_PROXY_IP'),
        'reverseProxyHeaderMultiValue' => 'last',
        'reverseProxySSL' => '*',
    ],
];
