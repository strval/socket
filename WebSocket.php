<?php

/**
 * WebSocket 示例
 * 想要看到socket效果，在线测试http://www.websocket-test.com/
 *
 * date 2020/5/20 10:30
 * by strval@qq.com
 */
class WebSocket
{
    private $host;      //主机
    private $port;      //端口
    private $socket;    //主socket
    private $client;    //连接池

    // 初始化
    public function __construct($host, $port)
    {
        // 存储信息，方便后面使用
        $this->host = $host;
        $this->port = $port;
        // 创建套接字
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($this->socket, $this->host, $this->port);
        socket_listen($this->socket, 200);
        // 放入连接池
        $this->client[] = $this->socket;
    }

    // 开始执行
    public function run()
    {
        // 循环获取socket，socket_select是阻塞的
        while (true) {
            // 从连接池中读取socket(这个连接词后面会根据情况而改变)
            $read = $this->client;
            // 获取socket，会阻塞(拿到的socket可能是主socket(新连接过来的socket)，也可能是已经连接过了的socket)
            socket_select($read, $write, $except, null);
            foreach ($read as $socket) {
                // 如果是主socket
                if ($socket == $this->socket) {
                    // 通过主socket拿到子socket
                    $newSocket = socket_accept($socket);
                    if ($newSocket === false) continue;
                    // 读取内容，后面用于握手
                    $msg = socket_read($newSocket, 2048);
                    if ($msg === false || $msg === "") continue;
                    // 握手处理
                    $status = $this->handshake($newSocket, $msg);
                    if ($status === false) continue;
                    // 以上都没问题就放入连接池
                    $this->client[] = $newSocket;
				} else {
                    // 连接过了的socket，读取内容
                    $msg = socket_read($socket, 2048);
                    // 如果读取失败(可能是客户端关闭了)就把该socket从连接池中移除
                    if ($msg === false || $msg === "") {
                        $key = array_search($socket, $this->client);
                        unset($this->client[$key]);
                        continue;
                    }
                    // 内容进行解码(websocket的内容是需要解码后我们才能看得懂的)
                    $msg = $this->deMsg($msg);
                    // 获取IP和端口(好区分发信息的人是谁)
                    socket_getpeername($socket, $host, $port);
                    $msg = "{$host}:{$port} Say：" . $msg;
                    // 把内容进转码(如果不转码就发送字符串视乎会出问题,客户端那边接收不了)
                    $sendMsg = $this->enMsg($msg);
                    // 循环连接池，把内容发给所有人(连接词中有主socket，记得排除主socket)
                    foreach ($this->client as $childSocket) {
                        if ($childSocket != $this->socket) {
                            socket_write($childSocket, $sendMsg, strlen($sendMsg));
                        }
                    }
                }
            }
        }
    }

    // 捂手处理
    protected function handshake($newSocket, $msg)
    {
        // 拆分成数组,键值对方式
        $headers = array();
        $lines = preg_split("/\r\n/", $msg);
        foreach($lines as $line)
        {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
            {
                $headers[$matches[1]] = $matches[2];
            }
        }
        // 拿到key，创建返回的key，sha1中的salt是固定值
        $secKey = @$headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $this->host\r\n" .
            "WebSocket-Location: ws://$this->host:$this->port/websocket/websocket\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        // 给socket返回握手数据
        return socket_write($newSocket, $upgrade, strlen($upgrade));
    }

    // 解码
    protected function deMsg($msg)
    {
        $len = null;
        $masks = null;
        $data = null;
        $decoded = null;
        $len = ord($msg[1]) & 127;
        if ($len === 126)  {
            $masks = substr($msg, 4, 4);
            $data = substr($msg, 8);
        } else if ($len === 127)  {
            $masks = substr($msg, 10, 4);
            $data = substr($msg, 14);
        } else  {
            $masks = substr($msg, 2, 4);
            $data = substr($msg, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    // 转码
    protected function enMsg($msg)
    {
        $a = str_split($msg, 125);
        if (count($a) == 1) {
            return "\x81" . chr(strlen($a[0])) . $a[0];
        }
        $ns = "";
        foreach ($a as $o) {
            $ns .= "\x81" . chr(strlen($o)) . $o;
        }
        return $ns;
    }
}

// 运行起来
(new WebSocket('0.0.0.0', 8888))->run();
