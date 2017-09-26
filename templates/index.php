<?php 
namespace Automater;

//require_once 'auto.php';
require_once 'Controller.php';

//$auto = new Automaton();

$auto = new Controller();

//$auto->touchServerFile();

$sites_list = $auto->getSiteSelectList();
$users_list = $auto->getSystemUsers();
$dbase_list = $auto->getSystemDbases();
$dbase_json = $auto->fetchSystemDbasesJson();
$cms_v_list = $auto->fetchCMSVList();
$update_sts = $auto->gatherUpdateStats();

?>
<!Doctype html>
<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script type="text/javascript">
_sites_json = <?php echo $sites_list[1] . ";\n" ; ?>
_cms_v_json = <?php echo $cms_v_list . ";\n"; ?>
_sys_dbs_js = <?php echo $dbase_json . ";\n"; ?>
_update_sts = <?php echo  $update_sts . ";\n"; ?>
_dbs_by_did = new Object;
</script>
<script src="auto.js"></script>
<link rel="stylesheet" href="auto.css">
</head>

<body>
<input type="hidden" id="current_working_site" value>
<input type="hidden" id="selected_site" />
<h3>Web Services CMS Documentation/Backup/Update Dashboard</h3>

<div class="wrapper">
	<div id="stats_div" class="stats_div">
		<h3>Stats:</h3>
		<ul id="stats_list" class="stats_list">
			<li class="stat_row stat_head">
				<div class="stat_datum company">Site</div>
				<div class="stat_datum staging_needs_update">S</div>
				<div class="stat_datum needs_update">P</div>
				<div class="stat_datum server">Server</div>
			</li>
		</ul>
	</div>
	<div id="form_div" class="form_div">
		<form action="automator.php" onsubmit="preventDefault()">
			<fieldset id="form_fieldset" disabled="disabled" class="form_fieldset">
			<div id="container" class="container">
				<h3>Site: <span id="site_disp"></span></h3>
				<div id="db_backup_step" class="db_backup_step todo_step">
					<h3>Backup dbs:</h3>
					<div id="vtm_contact">
						<input class="vtm_contact_name" type="text" id="vtm_contact_name" />
					</div>
					<div id="db_wrap" class="db_wrap">
						<div id="lables" class="labels">
							<p><label>Mode:</label>
							<p><label>Available sites:&nbsp;</label>
							<p><label>User:</label>
							<p><label>Database:</label>
						</div>
						<div id="fields" class="fields">
							<p></p>
							<p><input id="mode_test" type="radio" name="mode" value="test" checked="checked"><label for="mode_test">Testing</label>
							<input id="mode_prod" type="radio" name="mode" value="prod"><label for="mode_prod">Production</label>
							<p>
							
							<select name="site" id="site" onchange="updateDBList(); getSiteParams();">
								<option>--</option>
							</select>
							<span id="inactive_notifier" class="notifier"></span>
							<p>
								<select name="username" id="username">
									<?php foreach ($users_list as $key => $val): ?>
										<option <?php if($val['is_def']): ?>selected<?php endif; ?> value="<?php echo $val['username']; ?>"><?php echo $val['username']; ?></option>
									<?php endforeach; ?>
								</select>
							<p><select id="dbase" onchange="fillSite();">
								
							</select>
						</div>
						<div id="status" class="status">
							<h4>Last updated:</h4>
							<p><label for="last_updated_staging">Staging: </label><input onchange="onchangeStagingDate()" type="text" id="last_updated_staging">
							<input id="alt_staging_updated" type="hidden">
							<p><label for="last_updated_prod">Prod: </label><input onchange="onchangeProdDate()" type="text" id="last_updated_prod">
							<input id="alt_prod_updated" type="hidden">
							<p>
							<label for="queued">Update queued: </label>
							<input id="queued" type="checkbox" onchange="onchangeQueued()">
						</div>
					</div>
					<button type="button" style="display: none;" id="db_backup_do" onclick="doDbBackup(); return false;">Backup DB</button>
					<button type="button" style="display: none;" id="skip_db_backup" onclick="moveStepToDone('db_backup_step'); return false;">Next Step</button>
					<div class="staging_cms" id="staging_cms">
							<h4>Staging:</h4>
							
							<p><label for="cmss">CMS: </label><select id="cmss"></select> <label id="cmss_vers"></label>&nbsp;
							<button type="button" onclick="updateRecordedStagingToLatest()" title="Update to latest cms" id="set_latest">
								Set staging CMS version to latest
							</button>
							<p><label>Staging site: </label><input class="site_fields" type="text" disabled="disabled" id="staging_site">
							
							<p><label for="update_cms_v">Update CMS version to: </label><select id="update_cmss_v"></select>
							<button type="button" id="staging_bacup_do" onclick="doCmsBackup('staging'); return false;">Backup Staging</button>
							<div id="plugins" class="plugins">
								<ul id="plugin_list" class="plugin_list">
								</ul>
							</div>
						</div>
						<div class="prod_cms dimmed" id="prod_cms">
							<h4>Production:</h4>
							
							<p><label for="cms">CMS: </label><select id="cms"></select> <label id="cms_vers"></label>
							<button type="button" onclick="updateRecordedProdToLatest()" title="Update to latest cms" id="set_latest">
								Set prod CMS version to latest
							</button>
							<p><label>Site: </label><input class="site_fields" type="text" disabled="disabled" id="production_site">
							
							<p><label for="update_cms_v">Update CMS version to: </label><select id="update_cms_v"></select>
							<button type="button" id="production_bacup_do" style="display: none;" onclick="doCmsBackup('production'); return false;">Backup Production</button>
						</div>
					<div id="backup_log" class="backup_log">
							<h4>Previous backups for: <span id="db_name"></span></h4>
							<ul id="backups_ul"></ul>
					</div>
				</div>
				<div id="cms_backup_step" class="cms_backup_step todo_step">
					
					<div id="secondary_controls" class="hidden">
						<h3>Backup CMS files:</h3>
						<div id="swap_div" class="swap_div">
						&nbsp;
						</div>
						
						<div id="skip_cms" class="skip_cms">
							<button type="button" onclick="skipCmsBackup(); return false;">Skip this step</button>
						</div>
					</div>
				</div>
				<div id="cms_update_step" class="cms_update_step todo_step" style="display: none;">
					<h3>Update the staging CMS:</h3>
					<label>Drop staging db:</label><button disabled="disabled">Drop staging db</button>
					<label>Copy production to staging:</label><button onclick="doDbCopy(); return false;">Copy production</button>
					<label>Drush up:</label><button disabled="disabled">Drush up --security-only</button>
				</div>
				<div id="completed_steps" class="done_column"></div>
			</div>
		</fieldset>
		</form>
	</div>
	<div id="notes" class="notes">
		<h3>Site notes</h3>
		<div id="notes_inner" class="notes_inner">
			<ul id="site_notes_list">
			
			</ul>
		</div>
		<h3>Site plugins 
			<button type="button" onclick="getPluginUpdateText()">Get update-able plugins</button>
			<button type="button" onclick="parsePluginsFile()">Re-parse plugins file</button>
		</h3>
		<div id="plugins_inner" >
			<div id="site_plugins_list" class="plugins_inner">
			</div>
		</div>
	</div>
</div>
<div id="hidden" style="display:none;"></div>

<div id="rds_dbs" class="rds_dbs" style="display: none;">
		
</div>

<div id="paste_plugins" title="Paste plugin list here" style="display: none;">
<textarea class="plugins_text" id="plugins_list" cols="65" rows="20"></textarea>
</div>

<div id="db_copy_modal" style="display: none;">
	<div class="flex_dbs">
		<div class="prod_dbs">
			<label for="prod_db_select">Production:</label>
			<select id="prod_db_select"></select>
		</div>
		<div class="db_arrow">
			&#x21d2;
		</div>
		<div class="stage_dbs">
			<label for="stage_db_select">Staging:</label>
			<select id="stage_db_select"></select>
		</div>
	</div>
</div>
<div id="login"></div>
<div id="plugin_dialog"></div>
</body>
</html>
























