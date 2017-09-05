<?php
/**
 * Created by PhpStorm.
 * User: denny
 * Date: 2017/8/18
 * Time: 14:14
 */
namespace voypay;

class trade extends voypaySdk{

    public function get($param){
        $appId = 'trade.get';
        return $this->callRemoteMethod($appId, $param);
    }
    public function submit($param){
        $appId = 'trade.credit.submit';
        return $this->callRemoteMethod($appId, $param);
    }
    public function forward($param){
        $appId = 'trade.credit.forward';
        return $this->callRemoteMethod($appId, $param);
    }
}

class refund extends voypaySdk{

    public function get($param){
        $appId = 'refund.get';
        return $this->callRemoteMethod($appId, $param);
    }
    public function submit($param){
        $appId = 'refund.submit';
        return $this->callRemoteMethod($appId, $param);
    }
}

class chargeback extends voypaySdk{
    public function get($param){
        $appId = 'chargeback.get';
        return $this->callRemoteMethod($appId, $param);
    }
}

abstract  class voypaySdk{
    protected $merId;
    protected $accesskey;
    protected $endpoint;
    protected $istest = false;
    protected $debug = false;
    protected $version = '2.0.1';
    const PROCESS_TIMEOUT = 300;
    const CONNECT_TIMEOUT = 300;

    public function __construct($config){
        $this->merId = $config['mer_no'];
        $this->accesskey = $config['accesskey'];
        $this->istest = ($config['mode'] =='live')  ? false : true;
        $this->debug = !empty($config['debug'])? true : false;

        if($this->istest){
            $this->endpoint = 'https://test.voypay.net/gateway/process/';
        }
        else{
            $this->endpoint = 'https://gateway.voypay.net/process/';
        }
    }

    public function getVersion(){
        return $this->version;
    }

    public function buildSign($assesskey,$content){
        return hash('sha256', $assesskey.$content);
    }

    // 签名验证
    public function checkSign($signature, $assesskey, $content){
        $newSign =   $this->buildSign($assesskey,$content);
        return strtolower($signature) == strtolower($newSign);
    }


    public function callRemoteMethod($appId, $param = array()){

        $merId = $this->merId;
        $accesskey  = $this->accesskey;
        $url  = $this->endpoint;

        $content = trim(json_encode($param,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

        $headers =  array();
        $headers[] = "MerId: ".$merId;
        $headers[] = "AppId: ".$appId;
        $headers[] = "Signature: " . $this->buildSign($accesskey, $content);
        $headers[] = "Expect: ";

        if($this->debug){
            p($headers,'Voypay Request header');
            p($content,'Voypay Request body');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::PROCESS_TIMEOUT);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_REFERER, 'http://localhost/');
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        $res = curl_exec($ch);

        curl_close($ch);

        list($header, $body) = explode( "\r\n\r\n",$res);
        $header = $this->parseHeader($header);
        if($this->debug) {
            p($res,'Voypay Response Info');
            p(curl_getinfo($ch),'curl_getinfo');
            p($header,'响应参数解析 header');
        }

        if(!$this->checkSign($header['signature'], $accesskey, $body)){
            return false;
            throw new \Exception('Voypay Response Signature error');
        }
        return json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
    }

    private function parseHeader($header){
        $res = explode("\r\n",$header);
        $t = current($res);
        list($protocol,$status) = explode(' ', $t);
        $backheader['protocol'] = $protocol;
        $backheader['status'] = $status;
        foreach ((array)$res as $i => $item){
            if($i ==0) {
                continue;
            };
            list($key,$value) = explode(': ', $item);
            if($key){
                $key = strtolower($key);
                $backheader[$key] = trim($value);
            }
        }
        return $backheader;
    }

}

function p($vars, $label = '', $return = false)
{
    if (ini_get('html_errors')) {
        $content = "<pre style='text-align:left'>\n";
        if ($label != '') {
            $content .= "<strong>{$label} :</strong>\n";
        }
        $content .= htmlspecialchars(print_r($vars, true));
        $content .= "\n</pre>\n";
    } else {
        $content = $label . " :\n" . print_r($vars, true);
    }
    if ($return) {
        return $content;
    }
    echo $content;
    return null;
}
