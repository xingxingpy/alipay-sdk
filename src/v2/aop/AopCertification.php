<?php

namespace Xingxingpy\AlipaySdk\v2\aop;

class AopCertification {
    /**
     * 验证支付宝公钥证书是否可信
     * @param $alipayCert mixed 支付宝公钥证书
     * @param $rootCert mixed 支付宝根证书
     */
    public static function isTrusted($alipayCert, $rootCert)
    {
        $alipayCerts = self::readPemCertChain($alipayCert);
        $rootCerts = self::readPemCertChain($rootCert);
        if (self::verifyCertChain($alipayCerts, $rootCerts)) {
            return self::verifySignature($alipayCert, $rootCert);
        } else {
            return false;
        }

    }

    public static function verifySignature($alipayCert, $rootCert)
    {
        $alipayCertArray = explode("-----END CERTIFICATE-----", $alipayCert);
        $rootCertArray = explode("-----END CERTIFICATE-----", $rootCert);
        $length = count($rootCertArray) - 1;
        $checkSign = self::isCertSigner($alipayCertArray[0] . "-----END CERTIFICATE-----", $alipayCertArray[1] . "-----END CERTIFICATE-----");
        if (!$checkSign) {
            $checkSign = self::isCertSigner($alipayCertArray[1] . "-----END CERTIFICATE-----", $alipayCertArray[0] . "-----END CERTIFICATE-----");
            if ($checkSign) {
                $issuer = openssl_x509_parse($alipayCertArray[0] . "-----END CERTIFICATE-----")['issuer'];
                for ($i = 0; $i < $length; $i++) {
                    $subject = openssl_x509_parse($rootCertArray[$i] . "-----END CERTIFICATE-----")['subject'];
                    if ($issuer == $subject) {
                        self::isCertSigner($alipayCertArray[0] . "-----END CERTIFICATE-----", $rootCertArray[$i] . $rootCertArray);
                        return $checkSign;
                    }
                }
            } else {
                return $checkSign;
            }
        } else {
            $issuer = openssl_x509_parse($alipayCertArray[1] . "-----END CERTIFICATE-----")['issuer'];
            for ($i = 0; $i < $length; $i++) {
                $subject = openssl_x509_parse($rootCertArray[$i] . "-----END CERTIFICATE-----")['subject'];
                if ($issuer == $subject) {
                    $checkSign = self::isCertSigner($alipayCertArray[1] . "-----END CERTIFICATE-----", $rootCertArray[$i] . "-----END CERTIFICATE-----");
                    return $checkSign;
                }
            }
            return $checkSign;
        }
    }

    public static function readPemCertChain($cert)
    {
        $array = explode("-----END CERTIFICATE-----", $cert);
        $certs[] = null;
        for ($i = 0; $i < count($array) - 1; $i++) {
            $certs[$i] = openssl_x509_parse($array[$i] . "-----END CERTIFICATE-----");
        }
        return $certs;
    }

    public static function verifyCert($prev, $rootCerts)
    {
        $nowTime = time();
        if ($nowTime < $prev['validFrom_time_t']) {
            throw new \Exception("证书未激活");
        }
        if ($nowTime > $prev['validTo_time_t']) {
            throw new \Exception("证书已经过期");
        }
        $subjectMap = null;
        for ($i = 0; $i < count($rootCerts); $i++) {
            $subjectDN = self::array2string($rootCerts[$i]['subject']);
            $subjectMap[$subjectDN] = $rootCerts[$i];
        }
        $issuerDN = self::array2string(($prev['issuer']));
        if (!array_key_exists($issuerDN, $subjectMap)) {
            throw new \Exception("证书链验证失败");
        }
        return true;
    }

    /**
     * 验证证书链是否是信任证书库中证书签发的
     * @param $alipayCerts mixed 目标验证证书列表
     * @param $rootCerts mixed 可信根证书列表
     */
    public static function verifyCertChain($alipayCerts, $rootCerts)
    {
        $sorted = self::sortByDn($alipayCerts);
        if (!$sorted) {
            throw new \Exception("证书链验证失败：不是完整的证书链");
        }
        //先验证第一个证书是不是信任库中证书签发的
        $prev = $alipayCerts[0];
        $firstOK = self::verifyCert($prev, $rootCerts);
        $length = count($alipayCerts);
        if (!$firstOK || $length == 1) {
            return $firstOK;
        }

        $nowTime = time();
        //验证证书链
        for ($i = 1; $i < $length; $i++) {
            $cert = $alipayCerts[$i];
            if ($nowTime < $cert['validFrom_time_t']) {
                throw new \Exception("证书未激活");
            }
            if ($nowTime > $cert['validTo_time_t']) {
                throw new \Exception("证书已经过期");
            }
        }
        return true;
    }

    /**
     * 将证书链按照完整的签发顺序进行排序，排序后证书链为：[issuerA, subjectA]-[issuerA, subjectB]-[issuerB, subjectC]-[issuerC, subjectD]...
     * @param $certs mixed 证书链
     */
    public static function sortByDn(&$certs)
    {
        //是否包含自签名证书
        $hasSelfSignedCert = false;
        $subjectMap = null;
        $issuerMap = null;
        for ($i = 0; $i < count($certs); $i++) {
            if (self::isSelfSigned($certs[$i])) {
                if ($hasSelfSignedCert) {
                    return false;
                }
                $hasSelfSignedCert = true;
            }
            $subjectDN = self::array2string($certs[$i]['subject']);
            $issuerDN = self::array2string(($certs[$i]['issuer']));
            $subjectMap[$subjectDN] = $certs[$i];
            $issuerMap[$issuerDN] = $certs[$i];
        }
        $certChain = null;
        self::addressingUp($subjectMap, $certChain, $certs[0]);
        self::addressingDown($issuerMap, $certChain, $certs[0]);

        //说明证书链不完整
        if (count($certs) != count($certChain)) {
            return false;
        }
        //将证书链复制到原先的数据
        for ($i = 0; $i < count($certs); $i++) {
            $certs[$i] = $certChain[count($certs) - $i - 1];
        }
        return true;
    }

    /**
     * 验证证书是否是自签发的
     * @param $cert mixed 目标证书
     */
    public static function isSelfSigned($cert)
    {
        $subjectDN = self::array2string($cert['subject']);
        $issuerDN = self::array2string($cert['issuer']);
        return ($subjectDN == $issuerDN);
    }


    public static function array2string($array)
    {
        $string = [];
        if ($array && is_array($array)) {
            foreach ($array as $key => $value) {
                $string[] = $key . '=' . $value;
            }
        }
        return implode(',', $string);
    }

    /**
     * 向上构造证书链
     * @param $subjectMap mixed 主题和证书的映射
     * @param $certChain mixed 证书链
     * @param $current mixed 当前需要插入证书链的证书，include
     */
    public static function addressingUp($subjectMap, &$certChain, $current)
    {
        $certChain[] = $current;
        if (self::isSelfSigned($current)) {
            return;
        }
        $issuerDN = self::array2string($current['issuer']);

        if (!array_key_exists($issuerDN, $subjectMap)) {
            return;
        }
        self::addressingUp($subjectMap, $certChain, $subjectMap[$issuerDN]);
    }

    /**
     * 向下构造证书链
     * @param $issuerMap mixed 签发者和证书的映射
     * @param $certChain mixed 证书链
     * @param $current mixed 当前需要插入证书链的证书，exclude
     */
    public static function addressingDown($issuerMap, &$certChain, $current)
    {
        $subjectDN = self::array2string($current['subject']);
        if (!array_key_exists($subjectDN, $issuerMap)) {
            return $certChain;
        }
        $certChain[] = $issuerMap[$subjectDN];
        self::addressingDown($issuerMap, $certChain, $issuerMap[$subjectDN]);
    }


    /**
     * Extract signature from der encoded cert.
     * Expects x509 der encoded certificate consisting of a section container
     * containing 2 sections and a bitstream.  The bitstream contains the
     * original encrypted signature, encrypted by the public key of the issuing
     * signer.
     * @param string $der
     * @return string on success
     * @return bool false on failures
     */
    public static function extractSignature($der = false)
    {
        if (strlen($der) < 5) {
            return false;
        }
        // skip container sequence
        $der = substr($der, 4);
        // now burn through two sequences and the return the final bitstream
        while (strlen($der) > 1) {
            $class = ord($der[0]);
            $classHex = dechex($class);
            switch ($class) {
                // BITSTREAM
                case 0x03:
                    $len = ord($der[1]);
                    $bytes = 0;
                    if ($len & 0x80) {
                        $bytes = $len & 0x0f;
                        $len = 0;
                        for ($i = 0; $i < $bytes; $i++) {
                            $len = ($len << 8) | ord($der[$i + 2]);
                        }
                    }
                    return substr($der, 3 + $bytes, $len);
                    break;
                // SEQUENCE
                case 0x30:
                    $len = ord($der[1]);
                    $bytes = 0;
                    if ($len & 0x80) {
                        $bytes = $len & 0x0f;
                        $len = 0;
                        for ($i = 0; $i < $bytes; $i++) {
                            $len = ($len << 8) | ord($der[$i + 2]);
                        }
                    }
                    $contents = substr($der, 2 + $bytes, $len);
                    $der = substr($der, 2 + $bytes + $len);
                    break;
                default:
                    return false;
                    break;
            }
        }
        return false;
    }

    /**
     * Get signature algorithm oid from der encoded signature data.
     * Expects decrypted signature data from a certificate in der format.
     * This ASN1 data should contain the following structure:
     * SEQUENCE
     *    SEQUENCE
     *       OID    (signature algorithm)
     *       NULL
     * OCTET STRING (signature hash)
     * @return bool false on failures
     * @return string oid
     */
    public static function getSignatureAlgorithmOid($der = null)
    {
        // Validate this is the der we need...
        if (!is_string($der) or strlen($der) < 5) {
            return false;
        }
        $bit_seq1 = 0;
        $bit_seq2 = 2;
        $bit_oid = 4;
        if (ord($der[$bit_seq1]) !== 0x30) {
            throw new \Exception('Invalid DER passed to getSignatureAlgorithmOid()');
        }
        if (ord($der[$bit_seq2]) !== 0x30) {
            throw new \Exception('Invalid DER passed to getSignatureAlgorithmOid()');
        }
        if (ord($der[$bit_oid]) !== 0x06) {
            throw new \Exception('Invalid DER passed to getSignatureAlgorithmOid()');
        }
        // strip out what we don't need and get the oid
        $der = substr($der, $bit_oid);
        // Get the oid
        $len = ord($der[1]);
        $bytes = 0;
        if ($len & 0x80) {
            $bytes = $len & 0x0f;
            $len = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $len = ($len << 8) | ord($der[$i + 2]);
            }
        }
        $oid_data = substr($der, 2 + $bytes, $len);
        // Unpack the OID
        $oid = floor(ord($oid_data[0]) / 40);
        $oid .= '.' . ord($oid_data[0]) % 40;
        $value = 0;
        $i = 1;
        while ($i < strlen($oid_data)) {
            $value = $value << 7;
            $value = $value | (ord($oid_data[$i]) & 0x7f);
            if (!(ord($oid_data[$i]) & 0x80)) {
                $oid .= '.' . $value;
                $value = 0;
            }
            $i++;
        }
        return $oid;
    }

    /**
     * Get signature hash from der encoded signature data.
     * Expects decrypted signature data from a certificate in der format.
     * This ASN1 data should contain the following structure:
     * SEQUENCE
     *    SEQUENCE
     *       OID    (signature algorithm)
     *       NULL
     * OCTET STRING (signature hash)
     * @return bool false on failures
     * @return string hash
     */
    public static function getSignatureHash($der = null)
    {
        // Validate this is the der we need...
        if (!is_string($der) or strlen($der) < 5) {
            return false;
        }
        if (ord($der[0]) !== 0x30) {
            throw new \Exception('Invalid DER passed to getSignatureHash()');
        }
        // strip out the container sequence
        $der = substr($der, 2);
        if (ord($der[0]) !== 0x30) {
            throw new \Exception('Invalid DER passed to getSignatureHash()');
        }
        // Get the length of the first sequence so we can strip it out.
        $len = ord($der[1]);
        $bytes = 0;
        if ($len & 0x80) {
            $bytes = $len & 0x0f;
            $len = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $len = ($len << 8) | ord($der[$i + 2]);
            }
        }
        $der = substr($der, 2 + $bytes + $len);
        // Now we should have an octet string
        if (ord($der[0]) !== 0x04) {
            throw new \Exception('Invalid DER passed to getSignatureHash()');
        }
        $len = ord($der[1]);
        $bytes = 0;
        if ($len & 0x80) {
            $bytes = $len & 0x0f;
            $len = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $len = ($len << 8) | ord($der[$i + 2]);
            }
        }
        return bin2hex(substr($der, 2 + $bytes, $len));
    }

    /**
     * Determine if one cert was used to sign another
     * Note that more than one CA cert can give a positive result, some certs
     * re-issue signing certs after having only changed the expiration dates.
     * @param string $cert - PEM encoded cert
     * @param string $caCert - PEM encoded cert that possibly signed $cert
     * @return bool
     */
    public static function isCertSigner($certPem = null, $caCertPem = null)
    {
        if (!function_exists('openssl_pkey_get_public')) {
            throw new \Exception('Need the openssl_pkey_get_public() function.');
        }
        if (!function_exists('openssl_public_decrypt')) {
            throw new \Exception('Need the openssl_public_decrypt() function.');
        }
        if (!function_exists('hash')) {
            throw new \Exception('Need the php hash() function.');
        }
        if (empty($certPem) or empty($caCertPem)) {
            return false;
        }
        // Convert the cert to der for feeding to extractSignature.
        $certDer = self::pemToDer($certPem);
        if (!is_string($certDer)) {
            throw new \Exception('invalid certPem');
        }
        // Grab the encrypted signature from the der encoded cert.
        $encryptedSig = self::extractSignature($certDer);
        if (!is_string($encryptedSig)) {
            throw new \Exception('Failed to extract encrypted signature from certPem.');
        }
        // Extract the public key from the ca cert, which is what has
        // been used to encrypt the signature in the cert.
        $pubKey = openssl_pkey_get_public($caCertPem);
        if ($pubKey === false) {
            throw new \Exception('Failed to extract the public key from the ca cert.');
        }
        // Attempt to decrypt the encrypted signature using the CA's public
        // key, returning the decrypted signature in $decryptedSig.  If
        // it can't be decrypted, this ca was not used to sign it for sure...
        $rc = openssl_public_decrypt($encryptedSig, $decryptedSig, $pubKey);
        if ($rc === false) {
            return false;
        }
        // We now have the decrypted signature, which is der encoded
        // asn1 data containing the signature algorithm and signature hash.
        // Now we need what was originally hashed by the issuer, which is
        // the original DER encoded certificate without the issuer and
        // signature information.
        $origCert = self::stripSignerAsn($certDer);
        if ($origCert === false) {
            throw new \Exception('Failed to extract unsigned cert.');
        }
        // Get the oid of the signature hash algorithm, which is required
        // to generate our own hash of the original cert.  This hash is
        // what will be compared to the issuers hash.
        $oid = self::getSignatureAlgorithmOid($decryptedSig);
        if ($oid === false) {
            throw new \Exception('Failed to determine the signature algorithm.');
        }
        switch ($oid) {
            case '1.2.840.113549.2.2':
                $algo = 'md2';
                break;
            case '1.2.840.113549.2.4':
                $algo = 'md4';
                break;
            case '1.2.840.113549.2.5':
                $algo = 'md5';
                break;
            case '1.3.14.3.2.18':
                $algo = 'sha';
                break;
            case '1.3.14.3.2.26':
                $algo = 'sha1';
                break;
            case '2.16.840.1.101.3.4.2.1':
                $algo = 'sha256';
                break;
            case '2.16.840.1.101.3.4.2.2':
                $algo = 'sha384';
                break;
            case '2.16.840.1.101.3.4.2.3':
                $algo = 'sha512';
                break;
            default:
                throw new \Exception('Unknown signature hash algorithm oid: ' . $oid);
                break;
        }
        // Get the issuer generated hash from the decrypted signature.
        $decryptedHash = self::getSignatureHash($decryptedSig);
        // Ok, hash the original unsigned cert with the same algorithm
        // and if it matches $decryptedHash we have a winner.
        $certHash = hash($algo, $origCert);
        return ($decryptedHash === $certHash);
    }

    /**
     * Convert pem encoded certificate to DER encoding
     * @return string $derEncoded on success
     * @return bool false on failures
     */
    public static function pemToDer($pem = null)
    {
        if (!is_string($pem)) {
            return false;
        }
        $cert_split = preg_split('/(-----((BEGIN)|(END)) CERTIFICATE-----)/', $pem);
        if (!isset($cert_split[1])) {
            return false;
        }
        return base64_decode($cert_split[1]);
    }

    /**
     * Obtain der cert with issuer and signature sections stripped.
     * @param string $der - der encoded certificate
     * @return string $der on success
     * @return bool false on failures.
     */
    public static function stripSignerAsn($der = null)
    {
        if (!is_string($der) or strlen($der) < 8) {
            return false;
        }
        $bit = 4;
        $len = ord($der[($bit + 1)]);
        $bytes = 0;
        if ($len & 0x80) {
            $bytes = $len & 0x0f;
            $len = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $len = ($len << 8) | ord($der[$bit + $i + 2]);
            }
        }
        return substr($der, 4, $len + 4);
    }
}