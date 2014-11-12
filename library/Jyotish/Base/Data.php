<?php
/**
 * @link      http://github.com/kunjara/jyotish for the canonical source repository
 * @license   GNU General Public License version 2 or later
 */

namespace Jyotish\Base;

use Jyotish\Graha\Graha;
use Jyotish\Graha\Lagna;
use Jyotish\Bhava\Bhava;
use Jyotish\Base\Utils;
use Jyotish\Ganita\Math;

/**
 * Data class.
 *
 * @author Kunjara Lila das <vladya108@gmail.com>
 */
class Data {
    /**
     * Graha block
     */
    const BLOCK_GRAHA = 'graha';
    /**
     * Extra block
     */
    const BLOCK_EXTRA = 'extra';
    /**
     * Bhava block
     */
    const BLOCK_BHAVA = 'bhava';
    /**
     * User block
     */
    const BLOCK_USER  = 'user';
    /**
     * More block
     */
    const BLOCK_MORE  = 'more';

    /**
     * Data template.
     * 
     * @var array
     */
    protected $dataTemplate = [
        self::BLOCK_GRAHA => [
            'element' => ['longitude', 'latitude', 'speed']
        ],
        self::BLOCK_EXTRA => [
            'element' => ['longitude']
        ],
        self::BLOCK_BHAVA => [
            'element' => ['longitude']
        ],
        self::BLOCK_USER => ['gender']
    ];

    protected $dataRequired = [
        self::BLOCK_GRAHA, 
        self::BLOCK_EXTRA
    ];

    /**
     * Analyzed data.
     * 
     * @var array
     */
    protected $data;

    /**
     * Array with values ​​of the rashis in the bhavas.
     * 
     * @var array
     */
    protected $rashiInBhava = array();

    /**
     * Array with values ​​of the grahas in the bhavas.
     * 
     * @var array
     */
    protected $grahaInBhava = array();

    /**
     * Array with values ​​of the grahas in the rashis.
     * 
     * @var array
     */
    protected $grahaInRashi = array();

    /**
     * Constructor
     * 
     * @param array $ganitaData
     */
    public function __construct(array $ganitaData) {
        $this->checkData($ganitaData);

        foreach ($this->dataRequired as $block){
            foreach($this->data[$block] as $key => $params){
                if(!isset($params['rashi'])){
                    $units = Math::partsToUnits($params['longitude']);
                    $this->data[$block][$key]['rashi'] = $units['units'];
                    $this->data[$block][$key]['degree'] = $units['parts'];
                }
            }
        }

        if(!isset($this->data['bhava'])){
            $longitude = $this->data['extra'][Graha::KEY_LG]['longitude'];
            for($b = 1; $b <= 12; $b++){
                $this->data['bhava'][$b]['longitude'] = $longitude < 360 ? $longitude : $longitude - 360;
                $units = Math::partsToUnits($this->data['bhava'][$b]['longitude']);
                $this->data['bhava'][$b]['rashi'] = $units['units'];
                $this->data['bhava'][$b]['degree'] = $units['parts'];
                $longitude += 30;
            }
        }

        $this->rashiInBhava = $this->getRashiInBhava();
    }

    /**
     * Check incoming data.
     * 
     * @throws Exception\InvalidArgumentException
     */
    protected function checkData($ganitaData)
    {
        foreach ($this->dataRequired as $block){
            if(!key_exists($block, $ganitaData))
                throw new Exception\InvalidArgumentException("Block '$block' is not found in the data.");
        }

        $checkBlock = function($block, $value){
            if($block == self::BLOCK_GRAHA) $elements = Graha::$graha;
            elseif($block == self::BLOCK_BHAVA) $elements = Bhava::$bhava;
            elseif($block == self::BLOCK_EXTRA) $elements = [Graha::KEY_LG => 'Lagna'];
            else $elements = array();

            foreach ($elements as $key => $name){
                if(!isset($value[$key]))
                    throw new Exception\InvalidArgumentException("Key '$key' in block '$block' is not found.");

                foreach ($this->dataTemplate[$block]['element'] as $propName){
                    if(!array_key_exists($propName, $value[$key]))
                        throw new Exception\InvalidArgumentException("Property '$propName' in element '$key $block' is not found.");
                }
            }
        };

        foreach ($ganitaData as $block => $value){
            if(defined('self::BLOCK_'.strtoupper($block))){
                $checkBlock($block, $value);
                $this->data[$block] = $value;
            }else{
                continue;
            }

        }
    }

    /**
     * Get Ganita data.
     */
    public function getData()
    {
        return $this->data;
    }
    
    /**
     * Calculation of extra lagnas.
     * 
     * @param null|array $lagnas Array of lagna keys
     * @throws Exception\InvalidArgumentException
     */
    public function calcExtraLagna(array $lagnas = null)
    {
        $Lagna = new Lagna($this->data);
        
        if(is_null($lagnas)){
            $lagnas = array_keys(Lagna::$lagna);
        }
        
        foreach ($lagnas as $key){
            if (!array_key_exists($key, Lagna::$lagna)){
                throw new Exception\InvalidArgumentException("Lagna with the key '$key' does not exist.");
            }
            $calcLagna = 'calc'.$key;
            $this->data['extra'][$key] = $Lagna->$calcLagna();
        }
    }

    /**
     * Get rashi in bhava.
     * 
     * @return array
     */
    public function getRashiInBhava() {
        foreach ($this->data['bhava'] as $bhava => $params) {
            $rashi = $params['rashi'];
            $this->rashiInBhava[$rashi] = $bhava;
        }
        return $this->rashiInBhava;
    }

    /**
     * Get graha in bhava.
     * 
     * @return array
     */
    public function getGrahaInBhava() {
        foreach ($this->data['graha'] as $graha => $params) {
            $rashi = $params['rashi'];

            $bhava = $this->rashiInBhava[$rashi];
            $direction = $params['speed'] > 0 ? 1 : -1;

            $this->grahaInBhava[$graha] = array(
                'bhava' => $bhava,
                'direction' => $direction,
            );
        }
        return $this->grahaInBhava;
    }

    /**
     * Get graha in rashi.
     * 
     * @return array
     */
    public function getGrahaInRashi() {
        foreach ($this->data['graha'] as $graha => $params) {
            $rashi = $params['rashi'];
            $direction = $params['speed'] > 0 ? 1 : -1;

            $this->grahaInRashi[$graha] = array(
                'rashi' => $rashi,
                'direction' => $direction,
            );
        }
        $this->grahaInRashi[Graha::KEY_LG]['rashi'] = $this->data['extra']['Lg']['rashi'];
        $this->grahaInRashi[Graha::KEY_LG]['direction'] = 1;

        return $this->grahaInRashi;
    }

    /**
     * Return graha label.
     * 
     * @param string $graha
     * @param int $labelType
     * @param string $userFunction
     * @return string
     */
    public function getGrahaLabel($graha, $labelType = 0, $userFunction = null) {
        $grahas = $this->getGrahaInBhava();

        switch ($labelType) {
            case 0:
                $label = $graha;
                break;
            case 1:
                if ($graha != Graha::KEY_LG) {
                    $grahaObject = Graha::getInstance($graha);
                    $label = Utils::unicodeToHtml($grahaObject->grahaUnicode);
                } else {
                    $label = $graha;
                }
                break;
            case 2:
                $label = call_user_func($userFunction, $graha);
                break;
            default:
                $label = $graha;
                break;
        }

        if ($graha == Graha::KEY_RA or $graha == Graha::KEY_KE or $graha == Graha::KEY_LG) {
            $grahaLabel = $label;
        }else{
            $grahaLabel = $grahas[$graha]['direction'] == 1 ? $label : '(' . $label . ')';
        }
        
        return $grahaLabel;
    }
}