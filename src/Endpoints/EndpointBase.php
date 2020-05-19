<?php

namespace StableCube\FileMuncherClient\Endpoints;

use StableCube\FileMuncherClient\Models\JsonWebToken;
use StableCube\FileMuncherClient\Models\VideoUploadSession;
use StableCube\FileMuncherClient\Models\FileManifest;
use StableCube\FileMuncherClient\Models\WorkspaceAccessToken;
use StableCube\FileMuncherClient\Services\OAuthTokenManager;
use StableCube\FileMuncherClient\Exceptions\DestinationNotWriteableException;
use StableCube\FileMuncherClient\Exceptions\FileMuncherHttpException;
use StableCube\FileMuncherClient\Exceptions\DownloadFailedException;

abstract class EndpointBase
{
    private $tokenManager;

    function __construct(OAuthTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    protected function getOauthTokenManager() : OAuthTokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Gets the api access token for backend access.
     * 
     * This token can create Workspaces and authorize file uploads
     * 
     * For security reasons this token should only be used by the backend server
     */
    protected function getBackendApiAccessToken() : JsonWebToken
    {
        return $this->getOauthTokenManager()->getBackendToken();
    }

    protected function curlPost(string $endpoint, string $dataString = null) : array
    {
        $token = $this->getBackendApiAccessToken();
        
        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');

        $contentLength = 0;
        if($dataString != null)
        {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
            $contentLength = strlen($dataString);
        }

die($token->accessToken);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token->accessToken,
            'Content-Type: application/json',
            'Content-Length: ' . $contentLength)
            );

        $response = curl_exec($curl);
        $responseJson = json_decode($response, true);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            $errorMessage = "Error: call to URL \"$endpoint\" failed with status \"$status\", response \"$response\", curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl) . "\n";
            throw new FileMuncherHttpException($errorMessage, curl_errno($curl));
        }

        curl_close($curl);

        return $responseJson;
    }

    protected function curlDelete(string $endpoint) : string
    {
        $token = $this->getBackendApiAccessToken();
        
        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token->accessToken,
            'Content-Type: application/json',
            ));

        $response = curl_exec($curl);
        $responseJson = json_decode($response, true);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            $errorMessage = "Error: call to URL \"$endpoint\" failed with status \"$status\", response \"$response\", curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl) . "\n";
            throw new FileMuncherHttpException($errorMessage, curl_errno($curl));
        }

        curl_close($curl);

        return $responseJson;
    }

    protected function curlGet(string $endpoint) : array
    {
        $token = $this->getBackendApiAccessToken();
        
        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token->accessToken,
            'Content-Type: application/json',
            ));

        $response = curl_exec($curl);
        $responseJson = json_decode($response, true);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            $errorMessage = "Error: call to URL \"$endpoint\" failed with status \"$status\", response \"$response\", curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl) . "\n";
            throw new FileMuncherHttpException($errorMessage, curl_errno($curl));
        }

        curl_close($curl);

        return $responseJson;
    }

    protected function curlDownloadFile(string $url, string $dest)
    {
        $pathInfo = pathinfo($dest);
        $destDirPath = $pathInfo['dirname'];
        if (!file_exists($destDirPath)) {
            mkdir($destDirPath, 0775, true);
        }

        if(!is_writable($destDirPath)){
            throw new DestinationNotWriteableException($destDirPath);
        }

        $options = array(
            CURLOPT_FILE => is_resource($dest) ? $dest : fopen($dest, 'w'),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL => $url,
            CURLOPT_FAILONERROR => true,
        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $return = curl_exec($ch);

        if ($return === false)
        {
            throw new DownloadFailedException($url, curl_error($ch));
        }
    }
}