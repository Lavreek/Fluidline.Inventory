<?php

define('ROOT', dirname(__DIR__) ."/");

$difference = ['.', '..', '.gitignore'];

$options = getopt("v:");

if (!isset($options['v'])) {
    echo "\nYou can select version by using parameter -v=\"*.*\"";
    echo "\nDo you want to continue? ";
    echo "\nN - Next, C - Cancel [N/C]: ";

    $breakPoint = true;
    while ($breakPoint) {
        $step = trim(fgets(STDIN));

        switch ($step) {
            case 'n' :
            case 'N' : {
                $breakPoint = false;
                break;
            }

            case 'c' :
            case 'C' : {
                die();
            }

            default : {
                echo "N - Next, C - Cancel [N/C]: ";
                break;
            }
        }
    }
}

$PHPCli = "";

if (isset($options['v'])) {
    $PHPCli = "php". $options['v'];

} else {
    $PHPCli = "php";
}

exec($PHPCli . " --version", $a);

if (count($a) > 0) {
    preg_match('#PHP 8\.#', $a[0], $matches);

    if (isset($matches[0])) {
        $imagesPath = ROOT ."public/products/images/";

        $images = array_diff(
            scandir($imagesPath), $difference
        );

        for ($i = 0; $i < count($images); $i++) {
            $command = $PHPCli ." \"". ROOT ."bin/console\" ImagesPuller";
            exec($command, $output, $commandResult);

            $execMessage = "\n". implode("\n", $output) ."\n";

            if ($commandResult === 1) {
                die("\nRunning command throw error in output". $execMessage);
            }

            if (is_array($output)) {
                if (count($output) > 0) {
                    echo $execMessage;
                }
            }
        }
    } else {
        throw new Exception('PHP version must be higher than 7.4.*');
    }
} else {
    throw new Exception('PHP CLI probably doesn\'t exist.');
}
