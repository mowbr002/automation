<?php 


namespace Automater;


class FIELD{
	const DATABASE = 'db';
	const REMOTE_HOST = '_remote_host_to_backup';
}

class CNAMES{
	const BLUE = 'blue';
	const RED  = 'red';
	const GREEN = 'green';
	const CYAN = 'cyan';
	const BLACK = 'black';
	const YELLOW = 'yellow';
	const GRAY = 'light_gray';
}

class BUDIR{
	const ROOT_DIR = "/var/tmp/cms_backup_files/";
}



class DATABASE{
	const SITES = 'vtm_sites';
	const DATAB	= 'dbs';
}

class SITE_FIELDS{
	const NAME	= 'company';
	const HOST	= 'server';
	const CMSS	= 'cms';
	const SVER	= 's_cms_v';
	const PVER	= 'cms_version';
	const PVVR	= 'cms_v';
	const DBLC	= 'db_location';
	const SURI	= 'staging_uri';
	const PURI	= 'uri';
	const SDBN	= 'name';
	const PDBN	= 'name';
	const GITR	= 'git_repo';
	const ADMN	= 'admin_id';
	const SIID	= 'id';
	const DISP	= 'disposition';
	const SITE	= 'site';
	const LUPS	= 'last_update_staging';
	const LUPP	= 'last_update_prod';
}



?>