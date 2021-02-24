<?php
namespace Hsd\Zello;

/**
 * ZelloWork server PHP API wrapper class.
 *
 * This class provides an easy way to interact with Zello server
 * from your PHP code to add, modify and delete users and channels.
 * Please note that all text values passed to the API must be in UTF-8 encoding
 * and any text data returned are in UTF-8 as well.
 *
 * @version 1.1.0
 * @see https://github.com/zelloptt/zellowork-server-api-libs/blob/master/php/zello_server_api.class.php
 */
class Api
{
    /**
     * API version
     */
    public string $version = "1.1.0";

    /**
     * Dataset returned by the most recent API call {Array}.
     * Use this array to retrieve the result of a successful API call, the field is not defined if the API call failed
     */
    public array $data = [];

    /**
     * Error code {Integer}.
     * Contains error code for the most recent failed API call
     */
    public int $errorCode = 0;

    /**
     * Error decription {String}.
     * Contains error description in English for the most recent failed API call
     */
    public string $errorDescription = '';

    /**
     * @var string
     */
    public string $curlErrorDescription = '';

    /**
     * Session ID {String}.
     * Session ID used to identify logged in client, typically you'll want to authenticate first, then store Session ID and reuse it later
     */
    public string $sid = '';

    /**
     * Last accessed API url {String}.
     * This field might be useful for API troubleshooting.
     */
    public string $lastUrl = '';

    /**
     * @var string
     */
    protected string $host;

    /**
     * @var string
     */
    protected string $apiKey;

    /**
     * @var mixed
     */
    protected $curlConnectionTimeout;

    /**
     * @var mixed
     */
    protected $curlExecutionTimeout;

    /**
     * Api constructor.
     * @param $host
     * @param $apiKey
     * @param null $sid
     * @param null $curlConnectionTimeout
     * @param null $curlExecutionTimeout
     */
    public function __construct(string $host, string $apiKey, string $sid = null, $curlConnectionTimeout = null,
                                $curlExecutionTimeout = null)
    {
        $this->host = $host;
        $this->apiKey = $apiKey;
        if (!empty($sid)) {
            $this->sid = $sid;
        }
        if (!empty($curlConnectionTimeout)) {
            $this->curlConnectionTimeout = $curlConnectionTimeout;
        }
        if (!empty($curlExecutionTimeout)) {
            $this->curlExecutionTimeout = $curlExecutionTimeout;
        }
        $this->verifyRequirements();
    }

    /**
     * A legacy constructor.
     * @param $host    {String} Loudtalks server hostname or IP address
     * @param $api_key {String} Loudtalks API key
     * @deprecated
     */
    public function ZelloServerAPI($host, $api_key)
    {
        $this->__construct($host, $api_key);
    }

    /**
     * API client authentication.
     * If authentication fails, use the errorCode and errorDescription attributes to get error details.
     * If authentication succeeds, $this->sid is set to the Session ID.
     * The Session ID is reusable so it's recommended that you save this value and use it for further API calls.
     * Once you are done using API call ZelloServerAPI::logout() to end the session and invalidate Session ID
     * @param $username {String} administrative user username
     * @param $password {String} administrative user password
     * @return {Boolean} operation success result
     * @see    logout()
     */
    public function auth($username, $password)
    {
        if (!$this->callAPI("user/gettoken")) {
            return false;
        }
        $token = $this->data["token"];
        $this->sid = $this->data["sid"];

        return $this->callAPI("user/login", [
            "username" => $username,
            "password" => md5(md5($password) . $token . $this->apiKey)
        ]);
    }

    /**
     * Ends session identified by $this->sid.
     * Use this method to terminate the API session
     * @return {Boolean} operation success result
     * @see    auth()
     */
    public function logout()
    {
        $res = $this->callAPI("user/logout");
        $this->sid = '';

        return $res;
    }

    /**
     * Gets the list of the users or detailed information regarding a particular user.
     * @param $username   {String} username of the user, for which the details are requested, if omitted users list is returned.
     * @param $is_gateway {Boolean} whether return users or gateways
     * @param $max        {Integer} maximum number of results to fetch
     * @param $start      {Integer} start index of results to fetch
     * @param $channel    {String} channel name
     * @return mixed operation success result
     */
    public function getUsers($username = "", $is_gateway = false, $max = 0, $start = 0, $channel = '')
    {
        $url = "user/get";
        if ($username) {
            $url .= "/login/" . urlencode($username);
        }
        if ($channel) {
            $url .= "/channel/" . urlencode($channel);
        }
        if ($is_gateway) {
            $url .= "/gateway/1";
        }
        if ($max) {
            $url .= "/max/" . $max;
        }
        if ($start) {
            $url .= "/start/" . $start;
        }

        return $this->callAPI($url);
    }

    /**
     * Gets the list of the channels or detailed information regarding a particular channel.
     * @param $name  {String} name of the channel, for which the details are requested. If omitted, the full channels list is returned.
     * @param $max   (integer) maximum number of results to fetch
     * @param $start (integer) start index of results to fetch
     * @return mixed operation success result
     */
    public function getChannels($name = "", $max = 0, $start = 0)
    {
        $url = "channel/get";
        if ($name) {
            $url .= "/name/" . urlencode($name);
        }
        if ($max) {
            $url .= "/max/" . $max;
        }
        if ($start) {
            $url .= "/start/" . $start;
        }

        return $this->callAPI($url);
    }

    /**
     * Adds users to a channel.
     * @param $where {String} name of the channel, where the users are added
     * @param $who   {Array} an array of usernames of the users to add
     * @return mixed operation success result
     * @see    removeFromChannel()
     */
    public function addToChannel($where, $who)
    {
        if (!is_array($who)) $who = [$who];
        $url = "user/addto/" . urlencode($where);
        $data = "login[]=" . implode("&login[]=", $who);

        return $this->callAPI($url, $data);
    }

    /**
     * Adds users to multiple channels.
     * @param $where {Array} channels names, where the users are added
     * @param $who   {Array} an array of usernames of the users to add
     * @return mixed operation success result
     */
    public function addToChannels($where, $who)
    {
        if (!is_array($who)) $who = [$who];
        $url = "user/addtochannels";
        $data = "users[]=" . implode("&users[]=", $who) . "&channels[]=" . implode("&channels[]=", $where);

        return $this->callAPI($url, $data);
    }

    /**
     * Removes users from a channel.
     * @param $where {String} name of the channel
     * @param $who   {Array} an array of the usernames of the users to remove
     * @return mixed operation success result
     * @see    addToChannel()
     */
    public function removeFromChannel($where, $who)
    {
        if (!is_array($who)) $who = [$who];
        $url = "user/removefrom/" . urlencode($where);
        $data = "login[]=" . implode("&login[]=", $who);

        return $this->callAPI($url, $data);
    }

    /**
     * Removes users from multiple channels.
     * @param $where {Array} names of the channels
     * @param $who   {Array} an array of the usernames of the users to remove
     * @return mixed operation success result
     */
    public function removeFromChannels($where, $who)
    {
        if (!is_array($who)) $who = [$who];
        $url = "user/removefromchannels";
        $data = "users[]=" . implode("&users[]=", $who) . "&channels[]=" . implode("&channels[]=", $where);

        return $this->callAPI($url, $data);
    }

    /**
     * Adds or updates the user.
     * If username exists, the user is updated, otherwise new user is created.
     * When adding a user, the "name" and "password" attributes are required.
     * When updating a user, "name" is required.
     * @param $user {Array} an array filled in with user details:
     * @li     name (required) - username
     * @li     password - password md5 hash
     * @li     email - e-mail address
     * @li     full_name - user alias
     * @li     job - user position
     * @li     admin - "true" or "false". Defines whether the user has access to the admin console
     * @li     limited_access - "true" or "false". Defines whether the user is restricted from starting 1-on-1 conversations or not
     * @li     gateway - set to "true" for adding a gateway, "false" -- normal user
     * @li     add - "true" or "false". If set to "true" the existing user will not be updated and error status will be returned instead.
     * @return mixed operation success result
     * @see    deleteUsers()
     */
    function saveUser($user = [])
    {
        $url = "user/save";
        $data = $this->createUrlString($user);

        return $this->callAPI($url, $data);
    }

    /**
     * Deletes users.
     * @param $who {Array} an array of the usernames of the users to remove
     * @return {Boolean} operation success result
     * @see    saveUser()
     */
    function deleteUsers($who)
    {
        $url = "user/delete";
        $data = "login[]=" . implode("&login[]=", $who);

        return $this->callAPI($url, $data);
    }

    /**
     * Adds a new channel.
     * @param $name      {String} channel name
     * @param $is_group  {Boolean} channel type true means group channels, false -- dynamic channel
     * @param $is_hidden {Boolean} channel when set to true in combination with $is_group set to true the channel of hidden group is created
     * @return {Boolean} operation success result
     * @see    deleteChannels()
     */
    function addChannel($name, $is_group = true, $is_hidden = false)
    {
        $url = "channel/add/name/" . urlencode($name) . "/shared/" . ($is_group ? "true" : "false") . "/invisible/" . ($is_hidden ? "true" : "false");

        return $this->callAPI($url);
    }

    /**
     * Deletes channels.
     * @param $what {Array} an array of the names of the channels to remove
     * @return {Boolean} operation success result
     * @see    addChannel()
     */
    function deleteChannels($what)
    {
        $url = "channel/delete";
        $data = "name[]=" . implode("&name[]=", $what);

        return $this->callAPI($url, $data);
    }

    /**
     * Get channel roles (simple format)
     * @param $name {String} channel name
     * @return mixed operation success result
     */
    function getChannelsRoles($name)
    {
        $url = "channel/roleslist/name/" . urlencode($name);

        return $this->callAPI($url);
    }

    /**
     * Adds or updates channel role
     * @param $channel  {String} channel name
     * @param $name     {String} new role name
     * @param $settings {Array} or {String} role settings in json format: '{"listen_only": false, "no_disconnect": true, "allow_alerts": false, "to": ["dispatchers"]}'
     * @return mixed operation success result
     */
    function saveChannelRole($channel, $name, $settings)
    {
        $url = "channel/saverole/channel/" . urlencode($channel) . "/name/" . urlencode($name);
        $params = [
            'settings' => is_array($settings) ? $this->jsonEncode($settings) : $settings
        ];

        return $this->callAPI($url, $params);
    }

    /**
     * Deletes channel role
     * @param $channel {String} channel name
     * @param $what    {Array} an array of the roles names to delete
     * @return {Boolean} operation success result
     */
    function deleteChannelRole($channel, $what)
    {
        $url = "channel/deleterole/channel/" . urlencode($channel);
        $data = "roles[]=" . implode("&roles[]=", $what);

        return $this->callAPI($url, $data);
    }

    /**
     *  Adds users to channel role
     * @param $channel {String} channel name
     * @param $name    {String} role name
     * @param $users   {Array} an array of the usernames to add to role in channel
     * @return mixed operation success result
     */
    function addToChannelRole($channel, $name, $users)
    {
        $url = "channel/addtorole/channel/" . urlencode($channel) . "/name/" . urlencode($name);
        $data = "login[]=" . implode("&login[]=", $users);

        return $this->callAPI($url, $data);
    }

    /**
     * @return bool
     */
    private function verifyRequirements(): bool
    {
        if (!function_exists("json_decode") || !function_exists("json_encode")) {
            $this->errorCode = 1000;
            $this->errorDescription = "Missing JSON support";

            return false;
        }
        if (!function_exists("curl_init")) {
            $this->errorCode = 1001;
            $this->errorDescription = "Missing CURL support";

            return false;
        }

        return true;
    }

    /**
     * @param $input
     * @return mixed|null
     */
    public function jsonDecode($input)
    {
        if (function_exists("json_decode")) {
            $dec = json_decode($input, true);
            if ($dec && !json_last_error()) {
                return $dec;
            }
        }

        return null;
    }

    /**
     * @param $value
     * @return string|null
     */
    public function jsonEncode($value)
    {
        if (function_exists("json_encode")) {
            $enc = json_encode($value);
            if ($enc && !json_last_error()) {
                return $enc;
            }
        }

        return null;
    }

    /**
     * @param $object
     * @return string
     */
    private function createUrlString($object)
    {
        return http_build_query($object);
    }

    /**
     * @param $command
     * @param array $data
     * @param false $returnRawRes
     * @param string|null $params
     * @return bool|string
     */
    private function callAPI($command, $data = [], $returnRawRes = false, string $params = null)
    {
        $this->data = [];
        $pref = "http://";
        if (
            substr($this->host, 0, 7) == 'http://' ||
            substr($this->host, 0, 8) == 'https://'
        ) {
            $pref = "";
        }
        $url = $pref . $this->host . "/" . $command . "?rnd=" . $this->makeId();
        if ($this->sid) {
            $url .= "&sid=" . $this->sid;
        }
        if (!empty($params)) {
            $url .= '&' . $params;
        }
        $this->lastUrl = $url;
        $rawRes = $this->sendRequest($url, $data);
        if ($returnRawRes) {
            return $rawRes;
        }

        $res = $this->jsonDecode($rawRes);

        if (is_array($res) && isset($res["status"])) {
            if ($res["status"] == "OK") {
                $this->data = $res;

                return true;
            } else {
                $this->errorCode = intval($res["code"]);
                $this->errorDescription = $res["status"];

                return false;
            }
        } else {
            $this->errorCode = 1010;
            $this->errorDescription = "API is not available" . ($this->curlErrorDescription ? (": " . $this->curlErrorDescription) : "");

            return false;
        }
    }

    /**
     * @param $url
     * @param array $data
     * @return bool|string
     */
    private function sendRequest($url, $data = [])
    {
        if (!function_exists('curl_init')) {
            return '';
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "Content-Type" => "application/x-www-form-urlencoded"]
        );
        if ($data) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        if ($this->curlConnectionTimeout) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $this->curlConnectionTimeout);

        }
        if ($this->curlExecutionTimeout) {
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, $this->curlExecutionTimeout);
        }

        // Get the response and close the cURL session.
        $response = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrorCode = curl_errno($curl);
        if ($responseCode != "200") {
            $this->curlErrorDescription = sprintf("Curl code: %s, curl error: %s", $responseCode, $curlErrorCode);

            return '';
        }
        curl_close($curl);

        return $response;
    }

    /**
     * @param int $len
     * @return string
     */
    private function makeId(int $len = 32): string
    {
        [$usec, $sec] = explode(' ', microtime());
        $seed = (float)$sec + ((float)$usec * 100000);

        srand($seed);

        $feed = "abcdefghijklmnopqrstuvwxyz0123456789";
        $out = "";
        for ($i = 0; $i < $len; $i++) {
            $out .= substr($feed, rand(0, strlen($feed) - 1), 1);
        }

        return $out;
    }

    /**
     * @param array $northeast
     * @param array $southwest
     * @param string|null $name
     * @param string|null $filter
     * @param int|null $start
     * @param int $max
     * @version 24/2/21
     * @author  David Lopez <dlopez@hsd.cl>
     */
    public function getLocations(array $northeast, array $southwest, string $name = null, string $filter = null,
                                 int $start = null, int $max = null)
    {
        $url = "location/get";

        $query = urlencode("northeast[]") . '=' . implode("&" . urlencode("northeast[]") . '=', $northeast);
        $query .= '&' . urlencode("southwest[]") . '=' . implode("&" . urlencode("southwest[]") . '=', $southwest);
        $params = [];
        if (!empty($name)) {
            $params['name'] = $name;
        }
        if (!empty($filter)) {
            $params['filter'] = $filter;
        }
        if (!empty($start)) {
            $params['start'] = $start;
        }
        if (!empty($max)) {
            $params['max'] = $max;
        }
        $query .= http_build_query($params);

        return $this->callAPI($url, [], false, $query);
    }
}
