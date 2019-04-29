<?php

include_once __DIR__ . '/../libs/helper.php';
include_once __DIR__ . '/../libs/PSStore.php';

/**
 * @property bool $ReceiveEncrypted
 * @property string $Buffer
 * @property string $Seed
 * @property string $LoggedIn
 */
class PS4 extends IPSModule
{
    use BufferHelper,
        DebugHelper,
        TCPConnection,
        DDPConnection,
        VariableProfile;

    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();

        //Register Propertys
        $this->RegisterPropertyString('IP', '');
        $this->RegisterPropertyString('Credentials', '');
        $this->RegisterPropertyBoolean('AutoLogin', true);
        $this->RegisterPropertyInteger('BootTime', 40);
        $this->RegisterPropertyInteger('UpdateTimerInterval', 20);
        $this->RegisterPropertyString('Games', '[]');
        //$this->RegisterTimer('PS4_UpdateActuallyStatus', 20000, "PS4_UpdateActuallyStatus($this->InstanceID);");
        $this->RegisterTimer('PS4_UpdateActuallyStatus', 0, 'PS4_UpdateActuallyStatus($_IPS[\'TARGET\']);');

        //Register Variablen
        $this->RegisterMediaObject('PS4_MediaCover', 'Cover', 1, $this->InstanceID, 0, true, 'Cover.png');
        //$this->RegisterVariableString('PS4_Cover', 'Cover', '~HTMLBox', 0);
        $this->RegisterVariableString('PS4_AppTitle', 'Spiel/App', '', 1);
        $this->RegisterVariableString('PS4_AppPublisher', 'Publisher', '', 2);
        $this->RegisterVariableString('PS4_AppGenre', 'Genre', '', 3);
        $this->RegisterVariableBoolean('PS4_Power', 'Status', '~Switch', 4);
        $this->RegisterControls();
        $this->RegisterVariableInteger('PS4_Controls', 'Controls', 'PS4.Controls', 6);
        //Client Socket
        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->Buffer = '';
        $this->Seed = '';
        $this->ReceiveEncrypted = false;
        $this->LoggedIn = false;

        $data = IPS_GetInstance($this->InstanceID);
        $this->SendDebug('ID', $data['ConnectionID'], 0);
        $this->RegisterMessage($data['ConnectionID'], 10505);

        if (!IPS_VariableProfileExists('PS4.Games')) {
            $this->RegisterProfileIntegerEx('PS4.Games', 'Database', '', '', array());
        }
        $this->RegisterVariableInteger('PS4_Game', 'Games', 'PS4.Games', 5);
        $this->EnableAction('PS4_Game');
        $this->EnableAction('PS4_Power');
        $this->EnableAction('PS4_Controls');
        $this->UpdateGamelist();
        $this->SetTimerInterval('PS4_UpdateActuallyStatus', $this->ReadPropertyInteger('UpdateTimerInterval') * 1000);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, "TS: $TimeStamp SenderID " . $SenderID . ' with MessageID ' . $Message . ' Data: ' . print_r($Data, true), 0);
        switch ($Message) {
            case IM_CHANGESTATUS:
                switch ($Data[0]) {
                    case IS_EBASE:
                        $this->Buffer = '';
                        $this->Seed = '';
                        $this->ReceiveEncrypted = false;
                        $this->LoggedIn = false;
                        IPS_SetProperty($SenderID, 'Open', false); //I/O Instanz soll aktiviert sein.
                        IPS_ApplyChanges($SenderID); //Neue Konfiguration übernehmen
                }
        }
    }

    public function ReceiveData($JSONString)
    {
        $ReceiveData = json_decode($JSONString);
        $DataIn = utf8_decode($ReceiveData->Buffer);
        if ($this->ReceiveEncrypted) { // Hier empfangende Daten entschlüsseln
            $this->SendDebug('Received Encrypted Data', $DataIn, 1); // 1 für default ist Hex-Ansicht
            $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
            $Data = openssl_decrypt($DataIn, 'AES-128-CBC', $random_seed, OPENSSL_RAW_DATA, $this->Seed); //Decrypt benutzt unser Passwort (random_seed) und als start IV den empfangenen Seed des PS4
            $this->SendDebug('Received Decrypted Data', $Data, 0); // 1 für default ist Hex-Ansicht
        } else { // Unverschlüsselte Daten.
            $this->SendDebug('Received Plain Data', $DataIn, 1); // 1 für default ist Hex-Ansicht
            $Data = $this->Buffer . $DataIn;
            $Len = unpack('V', substr($Data, 0, 4))[1];
            //$Data lang genug ?
            if ($Len > strlen($Data)) { // Nein zu kurz, ab in den Buffer.
                $this->Buffer = $Data;
                return;
            }
            $this->Buffer = substr($Data, $Len); // Rest in den Buffer
            //Empfangenes Paket parsen
            $Packet = substr($Data, 4, $Len);
            $Type = substr($Packet, 0, 4);
            $Payload = substr($Packet, 4);
            switch ($Type) {
                case 'pcco':
                    $this->SendDebug('Hello Request Answer', $Payload, 1);
                    $this->Seed = substr($Payload, 12, 16);
                    $this->SendDebug('Seed received', substr($Payload, 12, 16), 1);
                    break;
                default:
                    $this->SendDebug('unhandled type received', $Type, 0);
                    $this->SendDebug('unhandled payload received', $Payload, 0);
                    break;
            }
        }
    }

    /** Public Functions to control PS4-System */
    public function WakeUp()
    {
        $this->sendWakeup();
    }

    public function Register(int $pincode)
    {
        $this->Connect();
        IPS_Sleep(600);
        $this->_send_login_request($pincode);
        IPS_Sleep(500);
        //$this->Close();
    }

    public function Login()
    {
        $this->Connect();
        IPS_Sleep(400);
        $this->_send_login_request();
        IPS_Sleep(100);
        //$this->Close();
    }

    public function Standby()
    {
        if ($this->Connect()) {
            $this->SendDebug(__FUNCTION__ . 'Standby ???', '???', 0);
            IPS_Sleep(400);
            $this->_send_login_request();
            IPS_Sleep(100);
            $this->_send_standby_request();
            //$this->Close();
        }
    }

    public function StartTitle(string $title_id)
    {
        $this->Connect();
        IPS_Sleep(400);
        $this->_send_login_request();
        $this->_send_boot_request($title_id);
        //$this->Close();
    }

    public function RemoteControl(string $remote_key, int $hold_time = 0)
    {
        $this->Connect();
        IPS_Sleep(400);
        $this->_send_login_request();
        IPS_Sleep(400);
        $this->_send_remote_control_request('open_rc', 0);
        IPS_Sleep(400);
        $this->_send_remote_control_request($remote_key, 0);
        IPS_Sleep(200);
        $this->_send_remote_control_request('key_off', 0);
        IPS_Sleep(200);
        $this->_send_remote_control_request('close_rc', 0);
        //$this->Close();
    }

    public function UpdateActuallyStatus()
    {
        $PS4Status = $this->getStatus();

        //Actually PS4 Power Status
        if ($PS4Status['Power']) {
            SetValue(IPS_GetObjectIDByIdent('PS4_Power', $this->InstanceID), true);

            //Actually Game
            if (array_key_exists('RUNNING-APP-TITLEID', $PS4Status)) {
                $GamesListString = $this->ReadPropertyString('Games');
                if ($GamesListString != '') {
                    $Games = json_decode($GamesListString);
                    foreach ($Games as $key=>$Game) {
                        if ($Game->GameID == $PS4Status['RUNNING-APP-TITLEID']) {
                            $this->SendDebug('GameID', $Game->GameID, 0);
                            $PSGame = new PSStore($Game->GameID);
                            SetValue(IPS_GetObjectIDByIdent('PS4_Game', $this->InstanceID), $key + 1);
                            SetValue(IPS_GetObjectIDByIdent('PS4_AppTitle', $this->InstanceID), $PSGame->getGameName());
                            SetValue(IPS_GetObjectIDByIdent('PS4_AppPublisher', $this->InstanceID), $PSGame->getProviderName());
                            SetValue(IPS_GetObjectIDByIdent('PS4_AppGenre', $this->InstanceID), $PSGame->getGenre());
                            $this->GetCover($PSGame->getPicture());
                        }
                    }
                }
            } else {
                $this->GetCover();
                SetValue(IPS_GetObjectIDByIdent('PS4_Game', $this->InstanceID), 0);
                SetValue(IPS_GetObjectIDByIdent('PS4_AppTitle', $this->InstanceID), '');
                SetValue(IPS_GetObjectIDByIdent('PS4_AppPublisher', $this->InstanceID), '');
                SetValue(IPS_GetObjectIDByIdent('PS4_AppGenre', $this->InstanceID), '');
            }
        } else {
            $this->GetCover();
            SetValue(IPS_GetObjectIDByIdent('PS4_Game', $this->InstanceID), 0);
            SetValue(IPS_GetObjectIDByIdent('PS4_AppTitle', $this->InstanceID), '');
            SetValue(IPS_GetObjectIDByIdent('PS4_AppPublisher', $this->InstanceID), '');
            SetValue(IPS_GetObjectIDByIdent('PS4_AppGenre', $this->InstanceID), '');
            SetValue(IPS_GetObjectIDByIdent('PS4_Power', $this->InstanceID), false);
        }
    }

    /** internal private Functions */
    private function GetCover($URL = null)
    {
        if ($URL != null) {
            $Content = file_get_contents($URL);
        } else {
            $Content = file_get_contents(__DIR__ . '/../imgs/default_cover.png');
        }

        IPS_SetMediaContent($this->GetIDForIdent('PS4_MediaCover'), base64_encode($Content));  //Bild Base64 codieren und ablegen
        IPS_SendMediaEvent($this->GetIDForIdent('PS4_MediaCover')); //aktualisieren
        return;
    }

    private function UpdateGamelist()
    {
        $GamesListString = $this->ReadPropertyString('Games');
        if ($GamesListString != '') {
            if (IPS_VariableProfileExists('PS4.Games')) {
                IPS_DeleteVariableProfile('PS4.Games');
            }

            $Associations = array();
            $Value = 1;

            $Games = json_decode($GamesListString);
            foreach ($Games as $Game) {
                $Associations[] = array($Value++, $Game->GameName, '', -1);
                // associations only support up to 32 variables
                if ($Value === 33) {
                    break;
                }
            }
            $this->RegisterProfileIntegerEx('PS4.Games', 'Database', '', '', $Associations);
        }
    }

    private function RegisterControls()
    {
        if (IPS_VariableProfileExists('PS4.Controls')) {
            IPS_DeleteVariableProfile('PS4.Controls');
        }

        $Associations = array();
        $Associations[] = array(1, 'UP', '', -1);
        $Associations[] = array(2, 'DOWN', '', -1);
        $Associations[] = array(3, 'RIGHT', '', -1);
        $Associations[] = array(4, 'LEFT', '', -1);
        $Associations[] = array(5, 'ENTER', '', -1);
        $Associations[] = array(6, 'BACK', '', -1);
        $Associations[] = array(7, 'OPTION', '', -1);
        $Associations[] = array(8, 'PS', '', -1);
        $Associations[] = array(9, 'LOGIN', '', -1);
        $this->RegisterProfileIntegerEx('PS4.Controls', 'Move', '', '', $Associations);
    }

    /** IPS Functions */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'PS4_Game':
                $GamesListString = $this->ReadPropertyString('Games');
                $Games = json_decode($GamesListString);
                $Game = $Games[$Value - 1];
                $this->StartTitle($Game->GameID);
                break;
            case 'PS4_Power':
                if ($Value) {
                    if ($this->ReadPropertyBoolean('AutoLogin')) {
                        $this->Login();
                        SetValue(IPS_GetObjectIDByIdent($Ident, $this->InstanceID), true);
                    } else {
                        $this->SendDebug('Wakeup', 'Drin', 0);
                        $this->WakeUp();
                        SetValue(IPS_GetObjectIDByIdent($Ident, $this->InstanceID), true);
                    }
                } else {
                    $this->Standby();
                    SetValue(IPS_GetObjectIDByIdent($Ident, $this->InstanceID), false);
                }
                break;
            case 'PS4_Controls':
                switch ($Value) {
                    case 1:
                        $this->RemoteControl('up', 0);
                        break;
                    case 2:
                        $this->RemoteControl('down', 0);
                        break;
                    case 3:
                        $this->RemoteControl('right', 0);
                        break;
                    case 4:
                        $this->RemoteControl('left', 0);
                        break;
                    case 5:
                        $this->RemoteControl('enter', 0);
                        break;
                    case 6:
                        $this->RemoteControl('back', 0);
                        break;
                    case 7:
                        $this->RemoteControl('option', 0);
                        break;
                    case 8:
                        $this->RemoteControl('ps', 0);
                        break;
                    case 9:
                        $this->Login();
                        break;
                    default:
                        $this->SendDebug('PS4_Control', $Value . ' is an invalid control', 0);
                        return;
                }
        }
    }

    public function GetConfigurationForParent()
    {
        $JsonArray = array('Host' => $this->ReadPropertyString('IP'), 'Port' => 997, 'Open' => IPS_GetProperty(IPS_GetInstance($this->InstanceID)['ConnectionID'], 'Open'));
        $Json = json_encode($JsonArray);
        return $Json;
    }
}
