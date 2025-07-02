<?php

/*
 * Panopto PHP classes generator.
 *
 * This script is using WSDL to PHP classes converter tool
 * (https://github.com/wsdl2phpgenerator/wsdl2phpgenerator) to
 * generate PHP classess for Panopto API web services use.
 */

if (isset($_SERVER['REMOTE_ADDR'])) {
    die; // No access from web!
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
    $generator = new \Wsdl2PhpGenerator\Generator();
} else {
    echo "Composer dependencies seem missing, run 'php composer.phar install' in the current directory.\n";
    die;
}

// Set defaults.
$server = 'demo.hosted.panopto.com';
$version = '4.6';

// CLI options.
$options = getopt("h", ['host:', 'apiversion:', 'help']);

// Checking util.php CLI script usage.
$help = <<<HELP

Panopto PHP classes generator.

This script is generating PHP classess for Panopto API web services using WSDL interface.

Usage:
  php generate_phpclasses.php [-h|--help] [--host=value] [--apiversion=value]

Options:
--host       Hostname to use for WSDL endpoints (default: $server).
--apiversion API version to use (default: $version).

-h, --help       Print out this help

Example usage:
\$ php generate_phpclasses.php --host=panopto.host.org --apiversion=4.6

The above command will generate PHP classes in ./lib/Panopto/PublicAPI/4.6/ directory.

HELP;

// Read CLI options.
if (isset($options['help']) || isset($options['h'])) {
    echo $help;
    exit(0);
}

if (!empty($options['host'])) {
    $server = $options['host'];
}

if (!empty($options['apiversion'])) {
    $version = $options['apiversion'];
}

// Generate classes.
$destination = __DIR__ . '/lib/Panopto/PublicAPI/' . $version;

$webservices = [
    'AccessManagement',
    'Auth',
    'RemoteRecorderManagement',
    'SessionManagement',
    'UsageReporting',
    'UserManagement',
];

echo 'Using https://' . $server . '/Panopto/PublicAPI/' . $version . "/ for webservices interface.\n";

foreach ($webservices as $webservice) {
    echo "Generating \Panopto\\" . $webservice . " classes...\n";
    $generatorconfig = [
        'inputFile' => 'https://' . $server . '/Panopto/PublicAPI/' . $version . '/' . $webservice . '.svc?singlewsdl',
        'outputDir' => $destination . '/' . $webservice,
        'namespaceName' => 'Panopto\\' . $webservice,
    ];

    $generator->generate(new \Wsdl2PhpGenerator\Config($generatorconfig));
}

echo "\nPHP classess have been generated from Panopto API WSDL. Use " . $destination .
        "/<webservice>/autoload.php in your project.\n";
exit(0);
