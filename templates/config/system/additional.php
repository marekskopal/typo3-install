<?php

$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = (string)getenv('MYSQL_DATABASE');
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = (string)getenv('MYSQL_HOST');
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = (string)getenv('MYSQL_PASSWORD');
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = (string)getenv('MYSQL_USER');

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_password'] = (string)getenv('TYPO3_SMTP_PASSWORD');
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_server'] = (string)getenv('TYPO3_SMTP_SERVER') ?: 'email-smtp.eu-west-1.amazonaws.com:465';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_username'] = (string)getenv('TYPO3_SMTP_USERNAME');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = (string)getenv('TYPO3_CONTEXT') === 'Development' ? 1 : 0;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = (string)getenv('REVERSE_PROXY_IP');
