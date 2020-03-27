<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class zoneminder extends eqLogic {
  //public static $_widgetPossibility = array('custom' => true);

  public static function cron30() {
    zoneminder::getSynchro();
  }

  public function getSynchro() {
    if (config::byKey('user','zoneminder', '') == '' || config::byKey('password','zoneminder', '') == '') {
      return;
    }
    $addr = config::byKey('addr','zoneminder') . config::byKey('path','zoneminder');
    $uri = $addr . '/api/monitors.json';
    $uriapi =  $addr . '/api/host/daemonCheck.json';
    log::add('zoneminder', 'debug', $uri);

    $post = 'user=' . config::byKey('user','zoneminder') . '&pass=' . config::byKey('password','zoneminder') . '';
    $loginUrl = $addr . '/api/host/login.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $json_string = curl_exec($ch);

    log::add('zoneminder', 'debug', $json_string);
    $parsed_json = json_decode($json_string, true);

    $token = $parsed_json['access_token'];
    $uri = $uri."?token=$token";

    log::add('zoneminder', 'debug', 'Retour ' . print_r($uri,true));

    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_URL, $uri);
    $json_string = curl_exec($ch);

    log::add('zoneminder', 'debug', $json_string);

    curl_setopt($ch, CURLOPT_URL, $uriapi);
    $json_active = json_decode(curl_exec($ch),true);
    curl_close($ch);

    $zoneminder = self::byLogicalId('zoneminder', 'zoneminder');
    if (!is_object($zoneminder)) {
      $zoneminder = new zoneminder();
      $zoneminder->setEqType_name('zoneminder');
      $zoneminder->setLogicalId('zoneminder');
      $zoneminder->setName('API Zoneminder');
      $zoneminder->setIsEnable(true);
      $zoneminder->save();
    }
    $zoneminder->loadCmdFromConf('zoneminder');
    $zoneminder->checkAndUpdateCmd('active',$json_active['result']);

    $parsed_json = json_decode($json_string, true);
    foreach($parsed_json['monitors'] as $monitor) {

      log::add('zoneminder', 'debug', 'Retour ' . print_r($monitor,true));
      log::add('zoneminder', 'debug', 'Retour ' . print_r($monitor['Monitor']['Id'],true));

      $zoneminder = self::byLogicalId($monitor['Monitor']['Id'], 'zoneminder');
      if (!is_object($zoneminder)) {
        $zoneminder = new zoneminder();
        $zoneminder->setEqType_name('zoneminder');
        $zoneminder->setLogicalId($monitor['Monitor']['Id']);
        $zoneminder->setName($monitor['Monitor']['Name']);
        $zoneminder->setIsEnable(true);
        $zoneminder->setConfiguration('deviceid',$monitor['Monitor']['Id']);
      }
      $zoneminder->setConfiguration('name',$monitor['Monitor']['Name']);
      $zoneminder->setConfiguration('width',$monitor['Monitor']['Width']);
      $zoneminder->setConfiguration('height',$monitor['Monitor']['Height']);
      $zoneminder->setConfiguration('type',$monitor['Monitor']['Type']);
      $zoneminder->setConfiguration('controlable',$monitor['Monitor']['Controllable']);
      $zoneminder->setConfiguration('controlid',$monitor['Monitor']['ControlId']);
      $zoneminder->save();
      $zoneminder->loadCmdFromConf('camera');

      $zoneminder->checkAndUpdateCmd('function',$monitor['Monitor']['Function']);
      $zoneminder->checkAndUpdateCmd('active',$monitor['Monitor']['Enabled']);

      if (class_exists('camera')) {
        zoneminder::syncCamera($monitor['Monitor']['Id']);
      }
    }
  }

  public function loadCmdFromConf($type) {
    if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
      return;
    }
    $content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
    if (!is_json($content)) {
      return;
    }
    $device = json_decode($content, true);
    if (!is_array($device) || !isset($device['commands'])) {
      return true;
    }
    foreach ($device['commands'] as $command) {
      $cmd = null;
      foreach ($this->getCmd() as $liste_cmd) {
        if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
        || (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
          $cmd = $liste_cmd;
          break;
        }
      }
      if ($cmd == null || !is_object($cmd)) {
        $cmd = new zoneminderCmd();
        $cmd->setEqLogic_id($this->getId());
        utils::a2o($cmd, $command);
        $cmd->save();
      }
    }
  }

  public function syncCamera($deviceid) {
    $zoneminder = self::byLogicalId($deviceid, 'zoneminder');
    $url = config::byKey('addr','zoneminder');
    $url_parse = parse_url($url);
    $plugin = plugin::byId('camera');
    $camera_jeedom = eqLogic::byLogicalId('zm'.$deviceid, 'camera');
    if (!is_object($camera_jeedom)) {
      $camera_jeedom = new camera();
      $camera_jeedom->setDisplay('height', $zoneminder->getConfiguration('height'));
      $camera_jeedom->setDisplay('width', $zoneminder->getConfiguration('width'));
    }
    $camera_jeedom->setName('ZM ' . $zoneminder->getName());
    $camera_jeedom->setIsEnable($zoneminder->getConfiguration('enabled'));
    $camera_jeedom->setConfiguration('ip', $url_parse['host']);
    $stream = isset($url_parse['path']) ? $url_parse['path'] : config::byKey('path','zoneminder');
    $camera_jeedom->setConfiguration('urlStream',  $stream . '/cgi-bin/nph-zms?mode=single&monitor=' . $deviceid . '&user=#username#&pass=#password#');
    $camera_jeedom->setConfiguration('username', config::byKey('user','zoneminder'));
    $camera_jeedom->setConfiguration('password', config::byKey('password','zoneminder'));
    $camera_jeedom->setEqType_name('camera');
    $camera_jeedom->setConfiguration('protocole', $url_parse['scheme']);
    $camera_jeedom->setConfiguration('device', ' ');
    $camera_jeedom->setConfiguration('applyDevice', ' ');
    $port = isset($url_parse['port']) ? ':' . $url_parse['port'] : '';
    $port = str_replace(':','',$port);
    if ($port == '') {
      if ($url_parse['scheme'] == 'https') {
        $port = 443;
      } else {
        $port = 80;
      }
    }
    $camera_jeedom->setConfiguration('port', $port);
    $camera_jeedom->setLogicalId('zm'.$deviceid);
    $camera_jeedom->save();
  }

  public function sendConf($deviceid,$command) {


    if (config::byKey('user','zoneminder', '') == '' || config::byKey('password','zoneminder', '') == '') {
      return;
    }
    $addr = config::byKey('addr','zoneminder') . config::byKey('path','zoneminder');
    $uri = $addr . '/api/monitors/' . $deviceid . '.json';

    log::add('zoneminder', 'debug', 'SendConf ' . $uri . ' ' . $command);

    $post = 'user=' . config::byKey('user','zoneminder') . '&pass=' . config::byKey('password','zoneminder') . '&action=login&view=console';
    $loginUrl = $addr . '/api/host/login.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $json_string = curl_exec($ch);

    log::add('zoneminder', 'debug', $json_string);
    $parsed_json = json_decode($json_string, true);

    $token = $parsed_json['access_token'];
    $uri = $uri."?token=$token";
    log::add('zoneminder', 'debug', 'Retour ' . print_r($uri,true));

    curl_setopt($ch, CURLOPT_URL, $uri);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $command);
    $json_string = curl_exec($ch);

    log::add('zoneminder', 'debug', 'SendConf' . $json_string);

    curl_close($ch);
  }

  /*public function toHtml($_version = 'dashboard') {
  	$replace = $this->preToHtml($_version);
  	$version = jeedom::versionAlias($_version);
  	if ($this->getDisplay('hideOn' . $version) == 1) {
  		return '';
  	}
  	foreach ($this->getCmd('info') as $cmd) {
  		$replace['#' . $cmd->getLogicalId() . '_history#'] = '';
  		$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
  		if (strrpos($cmd->getLogicalId(), 'time') !== false) {
  			$replace['#' . $cmd->getLogicalId() . '#'] = substr_replace($cmd->execCmd(), ':', -2, 0);
  		} else {
  			$replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
  		}
  		$replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
  		if ($cmd->getIsHistorized() == 1) {
  			$replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
  		}
  	}

  	$templatename = $this->getConfiguration('type');
  	return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $templatename, 'zoneminder')));
  }*/
}

class zoneminderCmd extends cmd {
  public function execute($_options = null) {
    if ($this->getType() == 'info') {
      return;
    }
    if ($this->getSubType() == 'select') {
      $request = str_replace('#select#', $_options['select'], $this->getConfiguration('request'));
    } else {
      $request = $this->getConfiguration('request');
    }
    $eqLogic = $this->getEqLogic();
    zoneminder::sendConf($eqLogic->getConfiguration('deviceid'),$request);
    zoneminder::getSynchro();
  }
}

?>
