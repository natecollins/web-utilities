<?php
/****************************************************************************************

Copyright 2016 Nathan Collins. All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice, this list of
      conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright notice, this list
      of conditions and the following disclaimer in the documentation and/or other materials
      provided with the distribution.

THIS SOFTWARE IS PROVIDED BY Nathan Collins ``AS IS'' AND ANY EXPRESS OR IMPLIED
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL Nathan Collins OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those of the
authors and should not be interpreted as representing official policies, either expressed
or implied, of Nathan Collins.

*****************************************************************************************/

/****************************************
 * Use examples
 ****************************************

TODO

*/

# Include guard, for people who can't remember to use '_once'
if (!defined('__USERDATA_GUARD__')) {
    define('__USERDATA_GUARD__',true);

/**
 * Handles validation of 
 */
class UserData {
    // Data location
    private $sFieldName;
    private $sMethod;

    // Filters
    private $sRegExp;
    private $mRangeLow;
    private $mRangeHigh;
    private $iLengthMin;
    private $iLengthMax;
    private $bTruncateLength;
    private $aAllowed;
    private $bAllowedStrict;

    // Errors
    private $aErrors;

    /**
     * UserData 
     * @param string sFieldName The name of the field to parse/validate
     * @param string sMethod One of GET,POST,COOKIE,FILES; or ANY, which
     *          checks the previously mentioned method in the order listed.
     */
    function __construct($sFieldName, $sMethod="ANY") {
        $this->aErrors = array();
        $this->sMethod = "NONE";
        $this->sRegExp = null;
        $this->mRangeLow = null;
        $this->mRangeHigh = null;
        $this->iLengthMin = null;
        $this->iLengthMax = null;
        $this->bTruncateLength = false;
        $this->aAllowed = null;
        $this->bAllowedStrict = false;

        if (!is_string($sFieldName)) {
            $this->aErrors[] = "Invalid UserData field name specified; name must be a string.");
        }
        else {
            $this->sFieldName = $sFieldName;
        }

        $sMethod = strtoupper($sMethod);
        if ( in_array($sMethod, array('ANY','GET','POST','COOKIE','FILES')) ) {
            $this->sMethod = $sMethod;
        }

    }

    /**
     * Static constructor wrapper
     */
    static public function create($sFieldName, $sMethod="ANY") {
        return new UserData($sFieldName, $sMethod);
    }

    /**
     * Get the appropriate value given the requested method
     * @return string|null The string value, or null if not found
     */
    private function getValue() {
        $mValue = null;
        if ($mValue === null &&
          in_array($this->sMethod, array('ANY','GET')) &&
          array_key_exists($this->sFieldName, $_GET)) {
            $mValue = $_GET[$this->sFieldName];
        }
        if ($mValue === null &&
          in_array($this->sMethod, array('ANY','POST')) &&
          array_key_exists($this->sFieldName, $_POST)) {
            $mValue = $_POST[$this->sFieldName];
        }
        if ($mValue === null &&
          in_array($this->sMethod, array('ANY','COOKIE')) &&
          array_key_exists($this->sFieldName, $_COOKIE)) {
            $mValue = $_COOKIE[$this->sFieldName];
        }
        return $mValue;
    }

    public function getStr($mDefault=null) {
        $sValue = $this->getValue();
        if (!$this->matchesRegExp()) {
            $this->aErrors[] = "Value does not match required pattern.");
            $sValue = $mDefault;
        }
        $sValue = $this->applyLength($sValue);
        if ($sValue === null) {
            $sValue = $mDefault;
        }
        if (!$this->isAllowed($fVal)) {
            $sValue = $mDefault;
        }
        return $sValue;
    }

    public function getString($mDefault=null) {
        return $this->getStr($mDefault);
    }

    public function getInt($mDefault=null) {
        $iVal = null;
        $sRaw = $this->getValue();
        if (ctype_digit($sRaw)) {
            $iVal = intval($sRaw);
        }

        $mValue = $this->applyRange($mValue);
        if (!$this->isAllowed($iVal)) {
            $iVal = $mDefault;
        }

        if ($iVal === null) {
            $iVal = $mDefault;
        }
        return $iVal;
    }

    public function getInteger($mDefault=null) {
        return $this->getInt($mDefault);
    }

    public function getFloat($mDefault=null) {
        $fVal = null;
        $sRaw = $this->getValue();
        if (is_numeric($sRaw)) {
            $fVal = floatval($sRaw);
        }

        $mValue = $this->applyRange($mValue);
        if (!$this->isAllowed($fVal)) {
            $fVal = $mDefault;
        }

        if ($fVal === null) {
            $fVal = $mDefault;
        }
        return $fVal;
    }

    /**
     * Attempt to get a boolean value from the data
     * If the string is a '1' or 'true' (case-insensitive), returns true
     * @param mixed mDefault
     * @return bool|null Returns true or false based on the parsed value, or null if field name does not exist
     */
    public function getBool($mDefault=null) {
        $bVal = $mDefault;
        $sVal = $this->getValue();
        if ($sVal !== null) {
            $bVal = false;
            if (in_array(strtolower($sVal), array('1','true'))) {
                $bVal = true;
            }
        }
        return $bVal;
    }

    public function getBoolean($mDefault=null) {
        return $this->getBool($mDefault);
    }

    public function getArray($mDefault=null) {
        //TODO filterRegExp
        //TODO filterLength
        //TODO filterAllowed
        //TODO
    }

    public function getFile($mDefault=null) {
        //TODO
    }

    public function getFileArray($mDefault=null) {
        //TODO
    }

    public function filterRegExp($sRegExp) {
        $this->sRegExp = $sRegExp;
    }

    private function matchesRegExp($mValue) {
        return (is_string($mValue) && is_string($this->sRegExp) && preg_match($this->sRegExp, $mValue) === 1);
    }

    /**
     * Filter the value to be between two numbers (inclusive).
     * To NOT filter one of the numbers (minimum or maximum), set it to null
     * @param mixed mLow The minimum allowed value; integer, float, or null
     * @param mixed mHigh The maximum allowed value; integer, float, or null
     */
    public function filterRange($mLow, $mHigh) {
        $this->mRangeLow = $mLow;
        $this->mRangeHigh = $mHigh;
    }

    private function applyRange($mValue) {
        if (is_int($mValue)) {
            if ($this->mRangeLow !== null) {
                $mValue = max($mValue,intval($this->mRangeLow));
            }
            if ($this->mRangeHigh !== null) {
                $mValue = min($mValue,intval($this->mRangeHigh));
            }
        }
        if (is_float($mValue)) {
            if ($this->mRangeLow !== null) {
                $mValue = max($mValue,floatval($this->mRangeLow));
            }
            if ($this->mRangeHigh !== null) {
                $mValue = min($mValue,floatval($this->mRangeHigh));
            }
        }
        return $mValue;
    }

    /**
     * Filter the length of string values; optionally truncates if too long
     * To NOT have a maximum value, set iMax to null
     * @param int mLow The minimum length of a string
     * @param int|null mHigh The maximum length of a string; set to null to not have a maximum
     * @param bool bTruncate If set to true, will truncate string if over iMax with no error
     */
    public function filterLength($iMin, $iMax, $bTruncate=false) {
        $this->iLengthMin = $iMin;
        $this->iLengthMax = $iMax;
        $this->bTruncateLength = $bTruncate;
    }

    /**
     *
     * @return string|null The proper length value, or null if an invalid length
     */
    private function applyLength($sValue) {
        $mReturn = null;
        if (is_string($sValue)) {
            if (strlen($sValue) < $this->iLengthMin) {
                $this->aErrors[] = "Value is shorter than the minimum length.");
            }
            elseif ($this->iLengthMax !== null && strlen($sValue) > $this->iLengthMax) {
                if ($this->bTruncateLength) {
                    $mReturn = substr($mValue, 0, $this->iLengthMax);
                }
                else {
                    $this->aErrors[] = "Value is longer than the maximum length.");
                }
            }
            else {
                $mReturn = $sValue;
            }
        }
        return $mReturn;
    }

    /**
     * Filter to only allow specific values
     * @param mixed aAllowed An array of allowed values, or a single allowed value
     * @param bool bStrict If set to true, will enforce type checks (see in_array())
     */
    public function filterAllowed($aAllowed, $bStrict=false) {
        // Put single value into an array
        if (!is_array($aAllowed)) {
            $aAllowed = array($aAllowed);
        }
        $this->aAllowed = $aAllowed;
        $this->bAllowedStrict = $bStrict;
    }

    private function isAllowed($mValue) {
        return in_array($mValue, $this->aAllowed, $this->bAllowedStrict);
    }
}

} // Include guard end


?>
