<?php
/**
 * @SWG\Model(id="setting",required="setting, value, clientts")
 */
class setting
{
    function __construct() {
        $this->clientspesific = (bool)$this->clientspesific; // ;)
    }
	
    /**
     * @SWG\Property(name="settingid",type="string",description="The settings individual ID. Used for deleting settings.")
     */
    public $settingid;
	
    /**
     * @SWG\Property(name="setting",type="string",description="Setting string. Identifies what the setting relates to.")
     */
    public $setting;

    /**
     * @SWG\Property(name="value",type="string",description="The value of the setting")
     */
    public $value;

    /**
     * @SWG\Property(name="clientspesific",type="boolean",description="Whether the setting is clientspesific or not.")
     */
    public $clientspesific;
}
