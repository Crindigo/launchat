<?php

$instance = $_SERVER['argv'][1];
$folder = 'Instances/' . $instance;
$pack = json_decode(file_get_contents($folder . '/instance.json'));
$atconf = load_at_conf();

echo "Launching $pack->name\n";

$command = $atconf['javapath'] . '/bin/java ';
$command .= '-XX:-OmitStackTraceInFastThrow ';
$command .= '-Xms256M -Xmx' . $atconf['ram'] . 'M ';
$command .= '-XX:PermSize=' . $atconf['permGen'] . 'M ';
$command .= '-Duser.language=en ';
$command .= '-Duser.country=US ';
$command .= '-Dfml.log.level=INFO ';
$command .= '-Dapple.laf.useScreenMenuBar=true ';
$command .= '-Xdock:icon=' . __DIR__ . '/Configs/Resources/virtual/' . $pack->minecraftVersion . '/icons/minecraft.icns ';
$command .= '-Xdock:name=' . escapeshellarg($pack->name) . ' ';
$command .= '-Djava.library.path=' . __DIR__ . '/' . $folder . '/bin/natives ';

//
// classpath
//

$fulljars = array_map(function($jar) use ($folder) {
    return __DIR__ . '/' . $folder . '/bin/' . $jar;
}, explode(',', $pack->librariesNeeded));

array_unshift($fulljars, __DIR__ . '/' . $folder . '/jarmods/' . $pack->jarOrder);
array_unshift($fulljars, 'ATLauncher.jar');
$fulljars[] = __DIR__ . '/' . $folder . '/bin/minecraft.jar';
$classpath = implode(':', $fulljars);

$command .= '-cp ' . $classpath . ' ';

// ---

$command .= $pack->mainClass . ' ';

$auth = handle_auth();

$mcArgs = $pack->minecraftArguments;
$mcParams = [
    'auth_player_name' => $auth['username'],
    'version_name' => $pack->minecraftVersion,
    'game_directory' => __DIR__ . '/' . $folder,
    'assets_root' => __DIR__ . '/Configs/Resources',
    'assets_index_name' => $pack->assets,
    'auth_uuid' => $auth['uuid'],
    'auth_access_token' => $auth['accessToken'],
    'user_properties' => '{}',
    'user_type' => 'mojang',
];

foreach ( $mcParams as $key => $value ) {
    $mcArgs = str_replace('${' . $key . '}', $value, $mcArgs);
}

$command .= $mcArgs . ' ';
$command .= '--width=' . $atconf['windowwidth'] . ' ';
$command .= '--height=' . $atconf['windowheight'] . ' ';
$command .= $pack->extraArguments;

echo "Starting up Minecraft\n";
shell_exec($command);
echo "Done\n";

function load_at_conf()
{
    $lines = file('Configs/ATLauncher.conf');
    $props = [];
    foreach ( $lines as $line ) {
        $line = trim($line);
        if ( $line[0] == '#' ) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $props[$key] = $value;
    }

    return $props;
}

// mojang authentication api

function handle_auth()
{
    $cfg = open_config();
    $justLoggedIn = false;

    // if we don't have a valid access token, we need to get one with /authenticate
    if ( empty($cfg['accessToken']) ) {
        $ok = false;
        do {
            do {
                $username = ask('Username' . ($cfg['username'] ? " [{$cfg['username']}]" : '') . ':');
                if ( empty($username) && $cfg['username'] ) {
                    $username = $cfg['username'];
                }
            } while ( empty($username) );

            $cfg['username'] = $username;
            $password = ask('Password:');

            $resp = api_authenticate($username, $password, $cfg['clientToken']);
            if ( !isset($resp->error) ) {
                $cfg['accessToken'] = $resp->accessToken;
                $cfg['clientToken'] = $resp->clientToken;
                $cfg['uuid'] = $resp->selectedProfile->id;
                $cfg['mcName'] = $resp->selectedProfile->name;

                save_config($cfg);
                $ok = true;
            } else {
                echo "Could not authenticate!\n";
            }
        } while ( !$ok );

        $justLoggedIn = true;
    }

    // if we didn't just log in, refresh the access token
    if ( !$justLoggedIn ) {
        echo "Refreshing access token...\n";
        $resp = api_refresh($cfg['accessToken'], $cfg['clientToken']);
        if ( !isset($resp->error) ) {
            echo "OK\n";
            $cfg['accessToken'] = $resp->accessToken;
            $cfg['clientToken'] = $resp->clientToken;
            save_config($cfg);
        } else {
            echo "Could not refresh! Please restart and enter your credentials again.\n";
            $cfg['accessToken'] = '';
            $cfg['clientToken'] = '';
            save_config($cfg);
            exit;
        }
    }

    return [
        'username' => $cfg['mcName'],
        'uuid' => $cfg['uuid'],
        'accessToken' => $cfg['accessToken'],
    ];
}

function api_authenticate($username, $password, $clientToken)
{
    $json = [
        'agent' => [
            'name' => 'Minecraft',
            'version' => 1
        ],
        'username' => $username,
        'password' => $password,
        'clientToken' => $clientToken,
    ];

    return api_send('/authenticate', $json);
}

function api_refresh($accessToken, $clientToken)
{
    $json = [
        'accessToken' => $accessToken,
        'clientToken' => $clientToken,
    ];
    return api_send('/refresh', $json);
}

function api_send($path, $json)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, 'https://authserver.mojang.com' . $path);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $resp = curl_exec($ch);
    return json_decode($resp);
}

function random_token()
{
    return sha1(uniqid(mt_rand(), true));
}

function open_config()
{
    if ( is_file('launchat.json') ) {
        return json_decode(file_get_contents('launchat.json'), true);
    } else {
        return [
            'clientToken' => random_token(),
            'accessToken' => '',
            'username' => '',
            'uuid' => '',
            'mcName' => '',
        ];
    }
}

function save_config($data)
{
    file_put_contents('launchat.json', json_encode($data, JSON_PRETTY_PRINT));
}

function ask($q)
{
    echo $q, ' ';
    return trim(fgets(STDIN));
}
