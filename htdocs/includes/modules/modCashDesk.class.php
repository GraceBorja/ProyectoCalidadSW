<?php
/* Copyright (C) 2008-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *      \defgroup   pos       Module points of sale
 *      \brief      Module to manage points of sale
 *		\version	$Id$
 */

/**
 *      \file       htdocs/includes/modules/modCashDesk.class.php
 *      \ingroup    pos
 *      \brief      File to enable/disable module Point Of Sales
 */
include_once(DOL_DOCUMENT_ROOT ."/includes/modules/DolibarrModules.class.php");


/**
 *       \class      modCashDesk
 *       \brief      Class to describe and enable module Point Of Sales
 */
class modCashDesk extends DolibarrModules
{
	/**
	 *		\brief	Constructor. Define names, constants, boxes...
	 * 		\param	DB		Database handler
	 */
	function modCashDesk ($DB)
	{
		$this->db = $DB;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used module id).
		$this->numero = 50100;
		// Key text used to identify module (for permission, menus, etc...)
		$this->rights_class = 'cashdesk';

		$this->family = "products";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "CashDesk module";

		$this->revision = explode(' ','$Revision$');
		$this->version = 'dolibarr';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		$this->picto = 'generic';

		// Data directories to create when module is enabled
		$this->dirs = array();

		// Relative path to module style sheet if exists. Example: '/mymodule/mycss.css'.
		$this->style_sheet = '';

		// Config pages. Put here list of php page names stored in admmin directory used to setup module.
		$this->config_page_url = array("cashdesk.php");

		// Dependencies
		$this->depends = array("modBanque","modFacture","modProduit");	// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();			// List of modules id to disable if this one is disabled
		$this->phpmin = array(4,1);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(2,4);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("@cashdesk");

		// Constantes
		$this->const = array();

		// Boxes
		$this->boxes = array();

		// Permissions
		$this->rights = array();

		// Main menu entries
		$this->menus = array();			// List of menus to add
		$r=0;

		// This is to declare the Top Menu entry:
		$this->menu[$r]=array(	    'fk_menu'=>0,			// Put 0 if this is a top menu
									'type'=>'top',			// This is a Top menu entry
									'titre'=>'CashDeskMenu',
									'mainmenu'=>'cashdesk',
									'leftmenu'=>'1',		// Use 1 if you also want to add left menu entries using this descriptor. Use 0 if left menu entries are defined in a file pre.inc.php (old school).
									'url'=>'/cashdesk/index.php',
									'langs'=>'@cashdesk',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
									'position'=>100,
									'perms'=>1,		// Use 'perms'=>'1' if you want your menu with no permission rules
									'target'=>'',
									'user'=>0);				// 0=Menu for internal users, 1=external users, 2=both

		$r++;

		// This is to declare a Left Menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>'r=0',		// Use r=value where r is index key used for the top menu entry
		//							'type'=>'left',			// This is a Left menu entry
		//							'titre'=>'Title left menu',
		//							'mainmenu'=>'mymodule',
		//							'url'=>'/comm/action/index2.php',
		//							'langs'=>'mylangfile',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'perms'=>'$user->rights->mymodule->level1->level2',		// Use 'perms'=>'1' if you want your menu with no permission rules
		//							'target'=>'',
		//							'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
		// $r++;
	}


	/**
     *		\brief      Function called when module is enabled.
     *					The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *					It also creates data directories.
	 *      \return     int             1 if OK, 0 if KO
     */
	function init()
  	{
    	$sql = array();

		$result=$this->load_tables();

    	return $this->_init($sql);
  	}

	/**
	 *		\brief		Function called when module is disabled.
 	 *              	Remove from database constants, boxes and permissions from Dolibarr database.
 	 *					Data directories are not deleted.
	 *      \return     int             1 if OK, 0 if KO
 	 */
	function remove()
	{
    	$sql = array();

    	return $this->_remove($sql);
  	}


	/**
	*		\brief		Create tables and keys required by module
	* 					Files mymodule.sql and mymodule.key.sql with create table and create keys
	* 					commands must be stored in directory /mymodule/sql/
	*					This function is called by this->init.
	* 		\return		int		<=0 if KO, >0 if OK
	*/
	function load_tables()
	{
		return $this->_load_tables('/cashdesk/sql/');
	}

}
?>
