<?php

namespace ICT;

class ControllerAPI
{
    private $sessionAesKey;
    private $hostAddress;
    private $hostHttps;
    private $sessionCookies = '';

    public function __construct($host, $https = false)
    {
        $this->hostAddress = $host;
        $this->hostHttps = $https;
        $this->sessionAesKey = str_repeat("\0", 16); // 16 bytes of zeros
        $this->sessionCookies = '';
    }

    /**
     * Calculate SHA1 hash from string
     */
    public static function sha1FromString($sourceString)
    {
        return strtoupper(sha1($sourceString));
    }

    /**
     * XOR function equivalent to C# version
     */
    private static function xorFn($inputString, $number)
    {
        $numberBinary = str_pad(decbin($number), 32, '0', STR_PAD_LEFT);
        $charArray = str_split($inputString);
        $startPosition = strlen($numberBinary);
        $result = '';

        for ($i = 0; $i < count($charArray); $i++) {
            $charCode = ord($charArray[$i]) & 0xff;
            $startPosition = $startPosition == 0 ? strlen($numberBinary) - 8 : $startPosition - 8;
            $byteString = substr($numberBinary, $startPosition, 8);
            $byteNumber = bindec($byteString);
            $result .= sprintf("%02X", $charCode ^ $byteNumber);
        }

        return $result;
    }

    /**
     * Determine if encryption is needed
     */
    private function shouldEncrypt($parameters)
    {
        if ($this->hostHttps) {
            return false;
        }

        if (strpos($parameters, 'Command&Type=Session&SubType=InitSession') === 0 ||
            strpos($parameters, 'Command&Type=Session&SubType=CheckPassword') === 0) {
            return false;
        }

        return true;
    }

    /**
     * Encrypt parameters using AES
     */
    private function encrypt($parameters)
    {
        $method = 'AES-128-CBC';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($parameters, $method, $this->sessionAesKey, OPENSSL_RAW_DATA, $iv);
        
        $ivString = strtoupper(bin2hex($iv));
        $dataString = strtoupper(bin2hex($encrypted));
        
        return $ivString . $dataString;
    }

    /**
     * Decrypt response using AES
     */
    private function decrypt($data)
    {
        $method = 'AES-128-CBC';
        $dataBytes = hex2bin($data);
        $iv = substr($dataBytes, 0, 16);
        $encryptedData = substr($dataBytes, 16);
        
        return openssl_decrypt($encryptedData, $method, $this->sessionAesKey, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Make HTTP request using cURL
     */
    private function getResponseString($parameters)
    {
        $shouldEncrypt = $this->shouldEncrypt($parameters);
        if ($shouldEncrypt) {
            $parameters = $this->encrypt($parameters);
        }

        $protocol = $this->hostHttps ? 'https' : 'http';
        $url = "{$protocol}://{$this->hostAddress}/PRT_CTRL_DIN_ISAPI.dll";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        // Add cookies if we have them
        if (!empty($this->sessionCookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookies);
        }

        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            curl_close($ch);
            return '';
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        curl_close($ch);

        // Extract cookies from headers
        if (preg_match_all('/Set-Cookie:\s*([^;]+)/i', $headers, $matches)) {
            $this->sessionCookies = implode('; ', $matches[1]);
        }

        if ($shouldEncrypt) {
            $body = $this->decrypt($body);
        }

        return $body;
    }

    /**
     * Log in to the controller
     */
    public function logIn($username, $passwordHash)
    {
        // Step 1: Get first random number
        $sessionRandIdString = $this->getResponseString("Command&Type=Session&SubType=InitSession");
        $sessionRandIdValue = intval($sessionRandIdString);

        // Step 2: Calculate XOR operations and hashes
        $xorUsername = self::xorFn($username, $sessionRandIdValue + 1);
        $hashXorUsername = self::sha1FromString($xorUsername);
        $xorPasswordHash = self::xorFn($passwordHash, $sessionRandIdValue);
        $hashXorPasswordHash = self::sha1FromString($xorPasswordHash);

        // Step 3: Determine function and send credentials
        $checkPasswordFunction = $this->hostHttps ? "CheckPasswordServer" : "CheckPassword";
        $sessionRandIdString2 = $this->getResponseString(
            "Command&Type=Session&SubType={$checkPasswordFunction}&Name={$hashXorUsername}&Password={$hashXorPasswordHash}"
        );

        // Debug output like C# version
        echo "PHP DEBUG:\n";
        echo "passwordHash         = " . $passwordHash . "\n";
        echo "rand1                = " . $sessionRandIdValue . "\n";
        echo "xor_user (hex)       = " . $xorUsername . "\n";
        echo "hash_user            = " . $hashXorUsername . "\n";
        echo "xor_pass (hex)       = " . $xorPasswordHash . "\n";
        echo "hash_pass            = " . $hashXorPasswordHash . "\n";

        // Check for authentication failure
        if (strpos(trim($sessionRandIdString2), "FAIL") === 0) {
            echo "Error in authentication: {$sessionRandIdString2}\n";
            return false;
        }

        // For HTTP: Generate AES session key
        if (!$this->hostHttps) {
            $sessionRandIdValue2 = intval($sessionRandIdString2);
            $xorPasswordHash2 = self::xorFn($passwordHash, $sessionRandIdValue2);
            $hashXorPasswordHash2 = self::sha1FromString($xorPasswordHash2);
            $this->sessionAesKey = substr($hashXorPasswordHash2, 0, 16);
        }

        return true;
    }

    /**
     * Log out of the controller
     */
    public function logOut()
    {
        return $this->getResponseString("Command&Type=Session&SubType=CloseSession");
    }

    /**
     * Get controller settings
     */
    public function getControllerSettings()
    {
        $list = $this->getResponseString("Request&Type=Detail&SubType=GXT_CONTROLLERSETTINGS_TBL");
        return $this->parseQueryString($list);
    }

    /**
     * Get inputs table
     */
    public function getInputsTable()
    {
        $list = $this->getResponseString("Request&Type=List&SubType=GXT_INPUTS_TBL");
        return $this->parseQueryString($list);
    }

    /**
     * Set input value
     */
    public function setInputValue()
    {
        $list = $this->getResponseString("Command&Type=Submit&SubType=GXT_INPUTS_TBL&InputId=652&Name=RD5.1");
        return $this->parseQueryString($list);
    }

    /**
     * List inputs with status
     */
    public function listInputsStatus()
    {
        $list = $this->getResponseString("Request&Type=Status&SubType=GXT_INPUTS_TBL"); //GXT_INPUTS_TBL");
        $parsed = $this->parseQueryString($list);
        $result = [];

        foreach ($parsed as $key => $value) {
            if ($key !== null && strpos($key, 'Input') === 0) {
                $numericPart = substr($key, 5);
                if (is_numeric($numericPart)) {
                    $result[intval($numericPart)] = $value ?? '';
                }
            }
        }

        return $result;
    }

    /**
     * Get input detail by record ID
     */
    public function getInputDetail($recId)
    {
        $details = $this->getResponseString("Request&Type=Detail&SubType=GXT_INPUTS_TBL&RecId={$recId}");
        return $this->parseQueryString($details);
    }

    /**
     * List outputs
     */
    public function listOutputs()
    {
        $list = $this->getResponseString("Request&Type=List&SubType=GXT_PGMS_TBL");
        $parsed = $this->parseQueryString($list);
        $result = [];

        foreach ($parsed as $key => $value) {
            if (is_numeric($key)) {
                $result[intval($key)] = $value ?? '';
            }
        }

        return $result;
    }

    /**
     * Set output properties
     */
    public function setOutput($pgmId, $name)
    {
        $encodedName = urlencode($name);
        return $this->getResponseString("Command&Type=Submit&SubType=GXT_PGMS_TBL&PGMId={$pgmId}&Name={$encodedName}");
    }

    /**
     * Control output
     */
    public function controlOutput($recId, $command, $data1 = 0, $data2 = 0)
    {
        $parameters = "Command&Type=Control&SubType=GXT_PGMS_TBL&RecId={$recId}&Command={$command}";
        
        if ($command == 2) { // Activate for Time requires Data1
            $parameters .= "&Data1={$data1}";
        }

        return $this->getResponseString($parameters);
    }

    /**
     * Parse query string into associative array (PHP equivalent of HttpUtility.ParseQueryString)
     */
    private function parseQueryString($queryString)
    {
        $result = [];
        parse_str($queryString, $result);
        return $result;
    }
}