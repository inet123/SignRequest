<?php
namespace AtaneNL\SignRequest;

use anlutro\cURL\cURL;

class Client {

    const API_BASEURL = "https://signrequest.com/api/v1";

    public static $defaultLanguage = 'nl';

    /* @var $curl \anlutro\cURL\cURL */
    private $curl;
    private $token;
    private $subdomain; // the subdomain

    public function __construct($token, $subdomain= null) {
        $this->token = $token;
        $this->subdomain = $subdomain;
        $this->curl = new cURL();
    }

    /**
     * Send a document to SignRequest.
     * @param string $file The absolute path to a file.
     * @param string $identifier
     * @param string $callbackUrl
     * @return CreateDocumentResponse
     */
    public function createDocument($file, $identifier, $callbackUrl = null) {
        $file = curl_file_create($file);
        $response = $this->newRequest("documents")
                ->setHeader("Content-Type", "multipart/form-data")
                ->setData(['file'=>$file, 'external_id'=>$identifier, 'events_callback_url'=>$callbackUrl])
                ->send();
        if ($this->hasErrors($response)) {
            throw new Exceptions\SendSignRequestException($response);
        }
        return new CreateDocumentResponse($response);
    }

    /**
     * Send a sign request for a created document.
     * @param type $documentId
     * @param type $sender
     * @param type $recipients
     * @param type $message
     * @return uuid The document id
     */
    public function sendSignRequest($documentId, $sender, $recipients, $message = null) {
        foreach ( $recipients as &$r ) {
            if (!array_key_exists('language', $r)) {
                $r['language'] = self::$defaultLanguage;
            }
        }
        $response = $this->newRequest("signrequests")
                ->setHeader("Content-Type", "application/json")
                ->setData(json_encode([
                    "document"=>self::API_BASEURL . "/documents/" . $documentId . "/",
                    "from_email"=>$sender,
                    "message"=>$message,
                    "signers"=>$recipients
                    ]))
                ->send();
        $responseObj = json_decode($response->body);
        if ($this->hasErrors($response)) {
            throw new Exceptions\SendSignRequestException($response);
        }
        return $responseObj->uuid;
    }

    /**
     * Get a file.
     * @param uuid $documentId
     */
    public function getDocument($documentId) {
        $response = $this->newRequest("documents/{$documentId}", "get")->send();
        $responseObj = json_decode($response->body);
        if ($this->hasErrors($response)) {
            throw new Exceptions\SendSignRequestException($response);
        }
        return $responseObj;
    }



    /**
     * Create a new team.
     * @param string $name
     * @param slug $subdomain
     * @param string $callbackUrl
     */
    public function createTeam($name, $subdomain, $callbackUrl = null) {
        $response = $this->newRequest("teams")
                ->setHeader("Content-Type", "application/json")
                ->setData(json_encode([
                    "name"=>$name,
                    "subdomain"=>$subdomain,
                    "events_callback_url"=>$callbackUrl
                    ]))
                ->send();

        $responseObj = json_decode($response->body);
        if ($this->hasErrors($response)) {
            throw new Exceptions\SendSignRequestException("Unable to create team $name: ".$response);
        }
        return $responseObj->subdomain;
    }

    /**
     * Setup a base request object.
     * @param string $action
     * @param string $method post,put,get,delete,option
     * @return \anlutro\cURL\Request
     */
    private function newRequest($action, $method = 'post') {
        $baseRequest = $this->curl->newRawRequest($method, self::API_BASEURL . "/" . $action . "/")
            ->setHeader("Authorization", "Token " . $this->token)
            ->setData('subdomain', $this->subdomain);
        return $baseRequest;
    }

    /**
     * Check for error in status headers.
     * @param type $response
     */
    private function hasErrors($response) {
        return !preg_match('/^20\d$/', $response->statusCode);
    }

}