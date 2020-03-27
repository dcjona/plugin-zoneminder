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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>


<form class="form-horizontal">
  <div class="form-group">
    <fieldset>

      <form class="form-horizontal">
        <div class="form-group">
          <fieldset>

            <div class="form-group">
              <label class="col-lg-4 control-label">{{Adresse Zoneminder : }}</label>
              <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="addr" style="margin-top:5px" placeholder="exp : http://ipzoneminder"/>
              </div>
            </div>

            <div class="form-group">
              <label class="col-lg-4 control-label">{{Complément spécifique à Zoneminder (hors cameras) : }}</label>
              <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="path" style="margin-top:5px" placeholder="ex : /zm"/>
              </div>
            </div>

            <div class="form-group">
              <label class="col-lg-4 control-label">{{Utilisateur : }}</label>
              <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="user" style="margin-top:5px" placeholder="utilisateur zoneminder"/>
              </div>
            </div>

            <div class="form-group">
              <label class="col-lg-4 control-label">{{Mot de passe : }}</label>
              <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="password" style="margin-top:5px" placeholder="password" type="password"/>
              </div>
            </div>


          </div>
        </fieldset>
      </div>
    </form>

    <script>

    function zoneminder_postSaveConfiguration(){
      $.ajax({// fonction permettant de faire de l'ajax
      type: "POST", // methode de transmission des données au fichier php
      url: "plugins/zoneminder/core/ajax/zoneminder.ajax.php", // url du fichier php
      data: {
        action: "postSave",
      },
      dataType: 'json',
      error: function (request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function (data) { // si l'appel a bien fonctionné
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
    }
  });
}

</script>
