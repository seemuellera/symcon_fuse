<?php

// Klassendefinition
class Fuse extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","Fuse");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("SourceVariable",0);
		$this->RegisterPropertyString("CompareMode","IsTrue");
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		
		//Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'Fuse_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "SourceVariable", "caption" => "Source Variable");
		
		$form['elements'][] = Array(
								"type" => "Select", 
								"name" => "CompareMode", 
								"caption" => "Select Comparison Mode",
								"options" => Array(
									Array(
										"caption" => "IsTrue (Boolean) - Fuse triggers when source variable is true",
										"value" => "IsTrue"
									),
									Array(
										"caption" => "IsFalse (Boolean) - Fuse triggers when source variable is false",
										"value" => "IsFalse"
									)
								)
							);
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'FUSE_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Trigger", "onClick" => 'FUSE_Trigger($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Reset", "onClick" => 'FUSE_Reset($id);');
		
		// Return the completed form
		return json_encode($form);

	}
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);

		// Clean old references
		$referenceList = $this->GetReferenceList();
		foreach ($referenceList as $currentReference) {

			$this->UnregisterReference($currentReference);
		}

		// Clean old message registration
		$messagesList = $this->GetMessageList();
		foreach ($messagesList as $currentMessageVarId => $currentMessageIDs) {

			foreach ($currentMessageIDs as $currentMessageID) {

				$this->UnregisterMessage($currentMessageVarId, $currentMessageID);
			}
		}
		
		$this->RegisterMessage($this->ReadPropertyInteger("SourceVariable"), VM_UPDATE);
		$this->RegisterReference($this->ReadPropertyInteger("SourceVariable"));
		
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				$this->LogMessage("An undefined compare mode was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		$this->CheckSourceVariable();
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->CheckSourceVariable();
	}
	
	public function Trigger() {
		
		if (GetValue($this->GetIDForIdent("Status")) ) {
		
			SetValue($this->GetIDForIdent("Status"), false);
			$this->LogMessage("Fuse was triggered","DEBUG");
		}
	}
	
	public function Reset() {
		
		if (! GetValue($this->GetIDForIdent("Status")) ) {
		
			SetValue($this->GetIDForIdent("Status"), true);
			$this->LogMessage("Fuse was triggered","DEBUG");
		}
	}
	
	protected function CheckSourceVariable() {
		
		switch ($this->ReadPropertyString("CompareMode") ) {
			
			case "IsFalse":
				if (! GetValue($this->ReadPropertyInteger("SourceVariable")) ) {
					
					$this->Trigger();
				}
				break;
			case "IsTrue":
				if (GetValue($this->ReadPropertyInteger("SourceVariable")) ) {
					
					$this->Trigger();
				}
				break;
			default:
				$this->LogMessage("An undefined compare mode was used: " . $this->ReadPropertyString("CompareMode"),"CRIT");
		}
	}
}
