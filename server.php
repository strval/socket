<?php

// 主socket
$listen_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($listen_socket, '0.0.0.0', 8888);
socket_listen($listen_socket, 200);

// 把主socket放入client数组中
// 为了每次新客户端进来都能在client数组中
$client = [$listen_socket];
$i=0;

// 以上只会在PHP服务端执行

while (true) {
    // 每个新客户端进来 或 收到数据 都会循环一次。
    echo "while{$i}\n";
    $i++;
    // 把所有客户端包括主socket赋值在可读取socket数组中
    $read = $client;
    var_dump("WHILE CLIENT:");
    var_dump($client);
    var_dump("WHILE READ:");
    var_dump($read);

    // 这个函数很重要
    // 每个有新客户端进来 或者 是收到数据都会阻塞在这里(服务端这里会阻塞一次，等待客户端连接或者是接收数据)
    if (socket_select($read, $write, $except, null) > 0) {
        var_dump("SOCKET READ:");
        var_dump($read); //无论是新客户还是收到数据，read都是一维数组，并且只有一个指。如果是新客户进来，那么这个值就是主socket，如果是收到数据那么，这个值就是这个子socket
        echo "socket select\n";
        // $read中包含了主socket。如果有用户进来，判断主socket是否存在read中。
        // 妇女在则拿到子socket，并把子socket放入client中,clinet也包括主socket.
        // 清除主socket在read中
        if (in_array($listen_socket,$read)) {
            echo "in array\n";
            $client_socket = socket_accept($listen_socket);
            $client[] = $client_socket;
            $key = array_search($listen_socket, $read);
            unset($read[$key]);
        }
        if (count($read) > 0) {
            echo "count > 0\n";
            foreach ($read as $socket_item) {
                echo "read as socket item\n";
                $content = socket_read($socket_item, 2048);
                if ($content === '') {
                    $key = array_search($socket_item, $client);
                    unset($client[$key]);
                    break;
                }
                var_dump($content);
                foreach ($client as $client_socket) {
                    echo "client client socket\n";
                    if ($client_socket != $listen_socket && $client_socket != $socket_item) {
                        echo "client socket ...\n";
                        socket_write($client_socket, $content, strlen($content));
                    }
                }
            }
        }
    } else {
        echo "continue\n";
        continue;
    }
}

echo 'end';
