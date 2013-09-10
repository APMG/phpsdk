<?php
namespace Pmp\Sdk;

require_once('CollectionDocJsonLinks.php');
require_once(dirname(__FILE__) . '/../restagent/restagent.lib.php');
use restagent\Request as Request;

class CollectionDocJson
{
    /**
     * @param string $url
     *    URL for a Collection.doc+json document
     * @param string $accessToken
     *    access token retrieved from the authentication client
     */
    public function __construct($url, $accessToken) {
        $this->url = $url;
        $this->accessToken = $accessToken;

        // Retrieve the document from the given URL
        $document = $this->getDocument($url, $accessToken);
        if (empty($document)) {
            return;
        }

        // Map the document properties to this object's properties
        $properties = get_object_vars($document);
        foreach($properties as $name => $value) {
            $this->$name = $value;
        }
    }

    /**
     * Gets the set of links from the document that are associated with the given link relation
     * @param string $relType
     *     link relation of the set of links to get from the document
     * @return CollectionDocJsonLinks
     */
    public function links($relType) {
        $links = array();
        if (!empty($this->links->$relType)) {
            $links = $this->links->$relType;
        }
        return new CollectionDocJsonLinks($links, $this);
    }

    /**
     * Saves the current document
     * @return CollectionDocJson
     */
    public function save() {
        $this->putDocument($this->url, $this->accessToken);
    }

    /**
     * Gets the set of items from the document
     * @return CollectionDocJsonItems
     */
    public function items() {
        $items = array();
        if (!empty($this->items)) {
            $items = $this->items;
        }
        return new CollectionDocJsonItems($items, $this);
    }

    /**
     * Gets a default "search" relation link that has the given URN
     * @param string $urn
     *    the URN associated with the desired "search" link
     * @return CollectionDocJsonLink
     */
    public function search($urn) {
        $urnSearchLink = null;
        $searchLinks = $this->links('search');

        // Lookup rels by given URN if search links found in document
        if (!empty($searchLinks)) {
            $urnSearchLinks = $searchLinks->rels(array($urn));

            // Use the first link found for the given URN if found
            if (!empty($urnSearchLinks[0])) {
                $urnSearchLink = $urnSearchLinks[0];
            }
        }
        return ($urnSearchLink) ? $urnSearchLink : new CollectionDocJsonLink(null, $searchLinks);
    }

    /**
     * Does a GET operation on the given URL and returns a JSON object
     * @param $url
     *    the URL to use in the request
     * @param $accessToken
     *    the access token to use in the request
     * @return stdClass
     */
    private function getDocument($url, $accessToken) {
        $request = new Request();

        // GET request needs an authorization header with given access token
        $response = $request->header('Content-Type', 'application/json')
                            ->header('Authorization', 'Bearer ' . $accessToken)
                            ->get($url);

        // Response code must be 200 and data must be found in response in order to continue
        if ($response['code'] != 200 || empty($response['data'])) {
            return null;
        }
        $document = json_decode($response['data']);
        return $document;
    }

    /**
     * Does a PUT operation on the given URL using the internal JSON objects
     * @param $url
     *    the URL to use in the request
     * @param $accessToken
     *    the access token to use in the request
     * @return bool
     */
    private function putDocument($url, $accessToken) {

        // Construct the document from the allowable properties in this object
        $document = new \stdClass();
        $document->version = (!empty($this->version)) ? $this->version : null;
        $document->data = (!empty($this->data)) ? $this->data : null;
        $document->links = (!empty($this->links)) ? $this->links : null;

        $request = new Request();

        // PUT request needs an authorization header with given access token and
        // the JSON-encoded body based on the document content
        $response = $request->header('Content-Type', 'application/json')
                            ->header('Authorization', 'Bearer ' . $accessToken)
                            ->body(json_encode($document))
                            ->put($url);

        // Response code must be 202 in order to be successful
        if ($response['code'] != 202) {
            return false;
        }
        return true;
    }
}