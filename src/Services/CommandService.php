<?php

namespace Upaidpckg\Config\Services;


class CommandService
{
    /**
     * Gets full path to file in application.
     *
     * @param $fileName
     * @param $appName
     *
     * @return null|string
     */
    public function getFilePath($fileName, $appName)
    {
        $filePath = null;
        foreach (config('upaidpckg.keys_paths') as $file => $path) {
            if ($fileName === $file) {
                if ($fileName === '.env' || $fileName === 'frontend.json') {
                    $filePath = $path . $fileName;
                } else {
                    $filePath =  $path . $appName . '/' . $fileName;
                }
            }
        }

        return $filePath;
    }

    /**
     * Validates if data is in the correct format and not empty
     *
     * @param $data
     * @param $fileExtension
     *
     * @return bool
     */
    public function validate($data, $fileExtension)
    {
       switch ($fileExtension) {
            case 'crt':
                $return = openssl_csr_get_public_key($data) || openssl_x509_read($data);
                break;
            case 'env':
                $return = parse_ini_string($data);
                break;
            case 'key':
                $return = openssl_pkey_get_private($data,  \Config::get('masterpass.connection.oAuth.passphrase'));
                break;
            case 'json':
               return $data;
               break;
            default:
                $return = true;
        }

        return ($return !== false && !empty($data));
    }

    /**
     * Create backup directory if not exist
     *
     * @return bool
     */
    function makeBcDir()
    {
        $ret = @mkdir('config_backup');
        //chmod('config_backup', 0755);
        return $ret === true || is_dir('config_backup');
    }

}


