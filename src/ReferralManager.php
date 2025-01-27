<?php
namespace BrazilPOANorth\ReferralManager;

use WpOrg\Requests\Session;
use WpOrg\Requests\Cookies;

enum ReferenceType: int {
    case BOOK_OF_MORMON = 23;
    case MISSIONARY_VISIT = 134;
}

class ReferralManager {

    private Session $session;
    private string $username;
    private string $password;
    private string $sec_email;
    private int $mission_id;
    private $media_sec;
    private int $auth_expire;
    

    public function __construct(string $username, string $password, string $email, int $mission_id) {
        $this->session = new Session();
        $this->session->useragent = 'referral-manager';
        $this->username = $username;
        $this->password = $password;
        $this->sec_email = $email;
        $this->mission_id = $mission_id;

        $this->authenticate();
        $this->media_sec = $this->get_media_sec_info();
    }

    /**
     * Gets the churchofjesuschrist.org url for a given subdomain
     * 
     * @access private
     * @return string full url with subdomain
     * @param string $name
     */
    private function host(string $name) {
        return "https://{$name}.churchofjesuschrist.org";
    }

    /**
     * Authenticates the user, and then stores the authentication cookie in the session cookies to be used in subsequent calls
     * 
     * @access private
     */
    private function authenticate() {
        $login_url = $this->host('www');
        $html_resp = $this->session->get("{$login_url}/services/platform/v4/login")->body;
        $state_token = preg_match('/\"stateToken\":\"([^\"]+)\"/', $html_resp, $matches) ? $matches[1] : null;
        $state_token = str_replace('\\x2D', '-', $state_token);
        $id_url = $this->host('id');
        $this->session->post("{$id_url}/idp/idx/introspect", ['Content-Type' => 'application/json'], json_encode(["stateToken" => $state_token]));
        $state_handle = json_decode($this->session->post("{$id_url}/idp/idx/identify", ['Content-Type' => 'application/json'], json_encode(["identifier" => $this->username, "stateHandle" => $state_token]))->body, true)['stateHandle'];
        $challenge_resp = json_decode($this->session->post("{$id_url}/idp/idx/challenge/answer", ['Content-Type' => 'application/json'], json_encode(["credentials" => ["passcode" => $this->password], "stateHandle" => $state_handle]))->body, true);
        $this->session->get($challenge_resp["success"]["href"]);
        $access_token = $this->session->cookies["oauth_id_token"];
        $this->auth_expire = $access_token->attributes['max-age'];
        $this->session->cookies->offsetSet("owp", $access_token);
    }

    /**
     * Returns true if the auth cookie has expired
     */
    public function auth_expired() {
        return time() > $this->auth_expire;
    }

    /**
     * Returns the time in which the auth token expires
     */
    public function get_auth_expire() {
        return $this->auth_expire;
    }

    /**
     * Returns the information for the requested media secretary
     * 
     * @access private
     * @param string $email
     * @param int $mission_id
     */
    private function get_media_sec_info() {
        $mission = $this->get_mission_info();
        foreach ($mission['leadership'] as $leader) {
            if ($leader['missionary']['emailAddress'] == $this->sec_email) {
                return $leader['missionary'];
            }
        }
        return NULL;
    }

    public function get_mission_info() {
        $mission = json_decode($this->session->get($this->host('referralmanager') . "/services/mission/{$this->mission_id}")->body, true)['mission'];
        return $mission;
    }

    /**
     * Returns the designated area for a given address
     * 
     * @access public
     * @param string $address
     */
    public function get_area_for_address(string $address) {
        $designated_area = json_decode($this->session->get($this->host('referralmanager') . "/services/mission/assignment?address={$address}&langCd=por")->body, true);
        if ($designated_area['bestProsAreaId'] == NULL) {
            $designated_area = $this->get_area_for_location($designated_area['coordinates']);
        }
        return $designated_area;
    }
    
    /**
     * Returns the designated area for given location coordinates
     * 
     * @access public
     * @param string $coords
     */
    private function get_area_for_location(array $coords) {
        $designated_area = json_decode($this->session->get($this->host('referralmanager') . "/services/mission/assignment?coordinates={$coords[0]},{$coords[1]}&langCd=por")->body, true);
        return $designated_area;
    }


    /**
     * Creates a media reference based on the given information and sends it to the Referral Manager service
     * 
     * @access public
     * @param string $first_name
     * @param string $last_name
     * @param string $address
     * @param string $phone
     * @param string $email
     * @param BrazilPOANorth\ReferralManager\ReferenceType|int $reference_type
     * @param string $referral_note
     */
    public function create_and_send_reference(string $first_name, string $last_name, string $address, string $phone, string $email, ReferenceType|int $reference_type, string $referral_note){

        $designated_area = $this->get_area_for_address($address);
        $org_id = $designated_area["bestOrgId"];
        $pros_area_id = $designated_area["bestProsAreaId"];

        $data = [
            "payload" => [
                "offers" => [
                    [
                        "personGuid" => NULL,
                        "offerItemId" => is_int($reference_type) ? $reference_type : $reference_type->value, # 23 is book of mormon reference, 134 visita dos missionarios
                        "deliveryMethodId" => 1 # in person delivery
                    ]
                ],
                "referral" => [
                    "personGuid" => NULL,
                    "referralNote" => $referral_note, # referal note to be included with the reference
                    "createDate" => (time() + 60) * 1000,
                    "sentToLocalPersonGuid" => $this->media_sec["clientGuid"],
                    "sentToLocalAppId" => NULL,
                    "referralStatus" => "UNCONTACTED" # the reference has been uncontacted
                ],
                "household" => [
                    "stewardCmisId" => NULL,
                    "address" => $address, # address of the reference
                    "lat" => NULL, # if there should be specific latitude information, if not the system will automatically create the pin
                    "lng" => NULL, # if there should be specific longditude information, if not the system will automatically create the pin
                    "pinDropped" => NULL, # if there was a specific pin location specified
                    "locId" => 87, # Brazil location ID
                    "orgId" => $org_id, # ID of the closest ward or branch
                    "missionaryId" => NULL,
                    "modDate" => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),// datetime.datetime.now(datetime.UTC).strftime("%Y-%m-%dT%H:%M.%S0Z"), # time of creation,
                    "people" => [
                        [
                            "firstName" => $first_name, # first name of the reference
                            "lastName" => $last_name, # last name of the reference
                            "contactSource" => 15398, # found through mission media page
                            "preferredLangId" => 59, # perfered language portugues
                            "ageCatId" => NULL,
                            "preferredContactType" => NULL,
                            "preferredPhoneType" => "PHN_MOBILE", # sets mobile phone as the default contact method
                            "preferredEmailType" => "EMAIL_HOME",
                            "gender" => NULL,
                            "note" => "",
                            "tags" => [],
                            "foundByPersonGuid" => NULL,
                            "contactInfo" => [
                                "phoneNumbers" => [
                                    [
                                        "type" => "PHN_MOBILE",
                                        "number" => $phone,
                                        "textable" => true
                                    ]
                                ],
                                "emailAddresses" => [
                                    [
                                        "type" => "EMAIL_HOME",
                                        "address" => $email
                                    ]
                                ]
                            ],
                            "status" => 1,
                            "dropNotes" => NULL,
                            "prosAreaId" => $pros_area_id,
                            "changerId" => $this->media_sec["cmisId"]
                        ]
                    ],
                    "changerId" => $this->media_sec["cmisId"]
                ],
                "person" => [
                    "firstName" => $first_name, # first name of the reference
                    "lastName" => $last_name, # last name of the reference
                    "contactSource" => 15398, # this person was found through mission media page
                    "preferredLangId" => 59, # perfered language portugues
                    "ageCatId" => NULL,
                    "preferredContactType" => NULL,
                    "preferredPhoneType" => "PHN_MOBILE", # sets mobile phone as the default contact method
                    "preferredEmailType" => "EMAIL_HOME",
                    "gender" => NULL,
                    "note" => "",
                    "tags" => [],
                    "foundByPersonGuid" => NULL,
                    "contactInfo" => [
                        "phoneNumbers" => [
                            [
                                "type" => "PHN_MOBILE",
                                "number" => $phone,
                                "textable" => true
                            ]
                        ],
                        "emailAddresses" => [
                            [
                                "type" => "EMAIL_HOME",
                                "address" => $email
                            ]
                        ]
                    ],
                    "status" => 1,
                    "dropNotes" => NULL,
                    "prosAreaId" => $pros_area_id
                ],
                "follow" => [
                    $this->media_sec["cmisId"]
                ],
                "needsPrivacyNotice" => True
            ]
        ];
        $this->session->post($this->host("referralmanager") . "/services/referrals/sendtolocal", ['Content-Type' => 'application/json'], json_encode($data));
        return static::format_area_info($designated_area);
    }

    public function assign_referrals() {
        $unassigned_people = $this->get_unassigned_people();
        foreach ($unassigned_people as $person) {
            try {
                $designated_area = $this->get_area_for_address($person['address']);
                if ($designated_area['bestProsAreaId'] == NULL) {
                    continue;
                }
                $household = json_decode($this->session->get($this->host('referralmanager') . '/services/households/' . $person['householdGuid'])->body, true);
                $household['people'][0]['prosAreaId'] = $designated_area['bestProsAreaId'];
                $household['orgId'] = $designated_area['bestOrgId'];
                $household['modDate'] = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z');
                $household['people'][0]['changerId'] = $this->media_sec["cmisId"];
                $household['missionaryId'] = NULL;
                $household['changerId'] = $this->media_sec["cmisId"];

                $data = [
                    "payload" => [
                        "offers" => [],
                        "referral" => [
                            "personGuid" => NULL,
                            "referralNote" => NULL,
                            "createDate" => time() * 1000,
                            "sentToLocalPersonGuid" => $this->media_sec["clientGuid"],
                            "sentToLocalAppId" => NULL,
                            "referralStatus" => "UNCONTACTED"
                        ],
                        "household" => $household,
                        "person" => $household['people'][0],
                        "follow" => [$this->media_sec["cmisId"]],
                        "needsPrivacyNotice" => False
                    ]
                ];
                $status_code = $this->session->post($this->host("referralmanager") . "/services/referrals/sendtolocal", ['Content-Type' => 'application/json'], json_encode($data))->status_code;
                if ($status_code != 200){
                    continue;
                }
                print("Assigned {$person['firstName']} successfuly!");
            } catch (Exception $e) {
                continue;
            }   
        }
    }

    /**
     * Returns a list of all the media references in the Referral Manager system
     * 
     * @access public
     */
    public function get_all_references() { 
        return json_decode($this->session->get($this->host("referralmanager") . "/services/people/mission/{$this->mission_id}")->body, true)['persons'];
    }

    /**
     * Returns a list of unassigned references in the Referral Manager system
     * 
     * @access public
     */
    public function get_unassigned_people() {
        $unassigned_people = array();
        $people = $this->get_all_references();
        foreach ($people as $person) {
            if( $person['areaId'] == NULL) {
                $unassigned_people[] = $person;
            }
        }
        return $unassigned_people;
    }
    
    /**
     * Returns a list of all recent converts baptized through media
     * 
     * @access public
     */
    public function get_recent_converts() {
        $recent_converts = array();
        $people = $this->get_all_references();
        foreach ($people as $person) {
            if ($person['convert'] == true) {
                $recent_converts[] = $person;
            }
        }
        return $recent_converts;
    }
    
    /**
     * Returns information about a specific person
     * 
     * @access public
     * @param int $guid
     */
    public function get_person(int $guid) {
        return json_decode($this->session->get(this->host('referralmanager') . "/services/people/{$guid}")->body, true);
    }
    
    /**
     * Returns information about the houshold of a given guid
     * 
     * @param int $guid
     */
    public function get_household(int $guid) {
        return json_decode($this->session->get($this->host('referralmanager') . "/services/households/{$guid}")->body, true);
    }

    public static function format_area_info($area_info) {
        $area_info = $area_info['proselytingAreas'][0];
        $missionaries = $area_info['missionaries'];
        foreach ($missionaries as &$missionary) {
            $missionary = ucfirst(strtolower($missionary['missionaryType'])) . ' ' . $missionary['lastName'];
        }

        $missionary_phonenumbers = $area_info['areaNumbers'];

        foreach ($missionary_phonenumbers as &$number) {
            $number = preg_replace("/[^0-9]/", "", $number);
        }

        //return area info
        return array(
            'name' => $area_info['name'],
            'missionaries' => $missionaries,
            'phones' => $missionary_phonenumbers
        );
    }
}