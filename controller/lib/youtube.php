<?php
/**
 * ownCloud - ocDownloader
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE file.
 *
 * @author Xavier Beurois <www.sgc-univ.net>
 * @copyright Xavier Beurois 2015
 */

namespace OCA\ocDownloader\Controller\Lib;

function debugWrite($any) {
    $real = $any;
    if (! is_array($any)) {
        $real = [$any];
    }

    $h = fopen('/var/www/my.log', 'a');
    foreach ($real as $a) {
        $str = json_encode($a);
        fwrite($h, $str, strlen($str));
        fwrite($h, PHP_EOL, 1);
    }
    fclose($h);
}

class YouTube
{
    private $YTDLBinary = null;
    private $URL = null;
    private $ForceIPv4 = true;
    private $ProxyAddress = null;
    private $ProxyPort = 0;
    private $Handle;

    public function __construct($YTDLBinary, $URL, $Handle = false)
    {
        $this->YTDLBinary = $YTDLBinary;
        $this->URL = $URL;
        $this->Handle = $Handle === 'true';
    }

    public function setForceIPv4($ForceIPv4)
    {
        $this->ForceIPv4 = $ForceIPv4;
    }

    public function setProxy($ProxyAddress, $ProxyPort)
    {
        $this->ProxyAddress = $ProxyAddress;
        $this->ProxyPort = $ProxyPort;
    }

    public function getVideoData($ExtractAudio = false)
    {
        $Proxy = null;
        if (!is_null($this->ProxyAddress) && $this->ProxyPort > 0 && $this->ProxyPort <= 65536) {
            $Proxy = ' --proxy ' . rtrim($this->ProxyAddress, '/') . ':' . $this->ProxyPort;
        }


        //youtube multibyte support
        putenv('LANG=en_US.UTF-8');

        $ShellCommand = $this->YTDLBinary.' -i \''.$this->URL.'\' --get-url --get-filename'
            .($ExtractAudio?' -f bestaudio -x':' -f best').($this->ForceIPv4 ? ' -4' : '')
            .(is_null($Proxy) ? '' : $Proxy)
        ;
        debugWrite($ShellCommand);

        $Output = shell_exec($ShellCommand);

        $index=(preg_match('/&index=(\d+)/', $this->URL, $current))?$current[1]:1;

        if (!is_null($Output)) {
            $Output = explode("\n", $Output);
            debugWrite('Not null: ' . count($Output));
            debugWrite('Not null: ' . json_encode($Output));

            if (count($Output) >= 2) {
                $OutProcessed = array();
                $current_index = $this->Handle ? 0 : 1;
                for ($I = 0; $I < count($Output); $I++) {
                    if (mb_strlen(trim($Output[$I])) > 0) {
                        if (mb_strpos(urldecode($Output[$I]), 'https://') === 0
                                && (mb_strpos(urldecode($Output[$I]), '&mime=video/') !== false) || $this->Handle) {
                            $OutProcessed['VIDEO'] = $Output[$I];
                        } elseif (mb_strpos(urldecode($Output[$I]), 'https://') === 0
                                && mb_strpos(urldecode($Output[$I]), '&mime=audio/') !== false) {
                            $OutProcessed['AUDIO'] = $Output[$I];
                        } else {
                            $OutProcessed['FULLNAME'] = $Output[$I];
                        }
                    } else {
                        debugWrite('Got something weird...');
                    }
                 if ((!empty($OutProcessed['VIDEO']) || !empty($OutProcessed['AUDIO'])) && !empty($OutProcessed['FULLNAME']))
                    {
                        if ($index == $current_index)
                        {
                            break;
                        } else {
                            $OutProcessed = array();
                            $current_index++;
                        }
                    }
                }
                $OutProcessed['Shell'] = $ShellCommand;
                return $OutProcessed;
            }
        }
        return null;
    }

}
