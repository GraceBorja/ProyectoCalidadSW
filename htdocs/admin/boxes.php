<?php
/* Copyright (C) 2003-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
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
        \file       htdocs/admin/boxes.php
        \brief      Page d'administration/configuration des boites
        \version    $Id$
*/

require("./pre.inc.php");
include_once(DOL_DOCUMENT_ROOT."/includes/boxes/modules_boxes.php");
include_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");

$langs->load("admin");

if (!$user->admin)
  accessforbidden();

// D�finition des positions possibles pour les boites
$pos_array = array(0);                             // Positions possibles pour une boite (0,1,2,...)
$pos_name = array(0=>$langs->trans("Home"));       // Nom des positions 0=Homepage, 1=...
$boxes = array();

/*
 * Actions
 */
if ((isset($_POST["action"]) && $_POST["action"] == 'addconst'))
{
    dolibarr_set_const($db, "MAIN_BOXES_MAXLINES",$_POST["MAIN_BOXES_MAXLINES"],'',0,'',$conf->entity);
}

if ($_POST["action"] == 'add')
{
	$sql = "SELECT rowid";
  $sql.= " FROM ".MAIN_DB_PREFIX."boxes";
  $sql.= " WHERE fk_user=0 AND box_id=".$_POST["boxid"]." AND position=".$_POST["pos"];
  $resql = $db->query($sql);
  dol_syslog("boxes.php::search if box active sql=".$sql);
	if ($resql)
  {
  	$num = $db->num_rows($resql);
	  if ($num == 0)
	  {
	  	$db->begin();

			// Si la boite n'est pas deja active, insert with box_order=''
	    $sql = "INSERT INTO ".MAIN_DB_PREFIX."boxes (box_id, position, box_order, fk_user) values (".$_POST["boxid"].",".$_POST["pos"].", '', 0)";
			dol_syslog("boxes.php activate box sql=".$sql);
	    $resql = $db->query($sql);

		  // Remove all personalized setup when a box is activated or disabled
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."user_param";
		  $sql.= " WHERE param like 'MAIN_BOXES_%'";
			dol_syslog("boxes.php delete user_param sql=".$sql);
		  $resql = $db->query($sql);

			$db->commit();
		}
		
		Header("Location: boxes.php");
	  exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if ($_GET["action"] == 'delete')
{
	$db->begin();
	
  $sql = "DELETE FROM ".MAIN_DB_PREFIX."boxes";
  $sql.= " WHERE rowid=".$_GET["rowid"];
  $resql = $db->query($sql);

  // Remove all personalized setup when a box is activated or disabled
  $sql = "DELETE FROM ".MAIN_DB_PREFIX."user_param";
  $sql.= " WHERE param like 'MAIN_BOXES_%'";
  $resql = $db->query($sql);

	$db->commit();
}

if ($_GET["action"] == 'switch')
{
	// On permute les valeur du champ box_order des 2 lignes de la table boxes
  $db->begin();

  $objfrom=new ModeleBoxes($db);
  $objfrom->fetch($_GET["switchfrom"]);
  
  $objto=new ModeleBoxes($db);
  $objto->fetch($_GET["switchto"]);
    
  if (is_object($objfrom) && is_object($objto))
  {
  	$sql="UPDATE ".MAIN_DB_PREFIX."boxes set box_order='".$objto->box_order."' WHERE rowid=".$objfrom->rowid;
		//print "xx".$sql;
    $resultupdatefrom = $db->query($sql);
    if (! $resultupdatefrom) { dol_print_error($db); }
    $sql="UPDATE ".MAIN_DB_PREFIX."boxes set box_order='".$objfrom->box_order."' WHERE rowid=".$objto->rowid;
		//print "xx".$sql;
    $resultupdateto = $db->query($sql);
    if (! $resultupdateto) { dol_print_error($db); }
   }

   if ($resultupdatefrom && $resultupdateto)
   {
   	 $db->commit();
   }
   else
   {
   	 $db->rollback();
   }

}


llxHeader();

print_fiche_titre($langs->trans("Boxes"),'','setup');

print $langs->trans("BoxesDesc")." ".$langs->trans("OnlyActiveElementsAreShown")."<br>\n";

/*
 * Recherche des boites actives par defaut pour chaque position possible
 * On stocke les boites actives par defaut dans $boxes[position][id_boite]=1
 */

$actives = array();

$sql = "SELECT b.rowid, b.box_id, b.position, b.box_order,";
$sql.= " bd.rowid as boxid";
$sql.= " FROM ".MAIN_DB_PREFIX."boxes as b, ".MAIN_DB_PREFIX."boxes_def as bd";
$sql.= " WHERE b.box_id = bd.rowid";
$sql.= " AND bd.entity = ".$conf->entity;
$sql.= " AND b.fk_user=0";
$sql.= " ORDER by b.position, b.box_order";

$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;
	$decalage=0;
	while ($i < $num)
	{
		$var = ! $var;
		$obj = $db->fetch_object($resql);
		$boxes[$obj->position][$obj->box_id]=1;
		$i++;
		
		array_push($actives,$obj->box_id);

		if ($obj->box_order == '' || $obj->box_order == '0' || $decalage) $decalage++;
		// On renum�rote l'ordre des boites si l'une d'elle est � ''
		// This occurs just after an insert.
		if ($decalage)
		{
			$sql="UPDATE ".MAIN_DB_PREFIX."boxes set box_order=".$decalage." WHERE rowid=".$obj->rowid;
			$db->query($sql);
		}
	}

	if ($decalage)
	{
		// Si on a renumerote, on corrige champ box_order
		// This occurs just after an insert.
		$sql = "SELECT box_order";
		$sql.= " FROM ".MAIN_DB_PREFIX."boxes";
		$sql.= " WHERE length(box_order) <= 2";
		$result = $db->query($sql);

		if ($result)
		{
			while ($record = $db->fetch_array($result))
			{
				if (strlen($record['box_order']) == 1)
				{
					if (preg_match("/[13579]{1}/",substr($record['box_order'],-1)))
					{
						$box_order = "A0".$record['box_order'];
						$sql="update llx_boxes set box_order = '".$box_order."' where box_order = ".$record['box_order'];
						$resql = $db->query($sql);
					}
					else if (preg_match("/[02468]{1}/",substr($record['box_order'],-1)))
					{
						$box_order = "B0".$record['box_order'];
						$sql="update llx_boxes set box_order = '".$box_order."' where box_order = ".$record['box_order'];
						$resql = $db->query($sql);
					}
				}
				else if (strlen($record['box_order']) == 2)
				{
					if (preg_match("/[13579]{1}/",substr($record['box_order'],-1)))
					{
						$box_order = "A".$record['box_order'];
						$sql="update llx_boxes set box_order = '".$box_order."' where box_order = ".$record['box_order'];
						$resql = $db->query($sql);
					}
					else if (preg_match("/[02468]{1}/",substr($record['box_order'],-1)))
					{
						$box_order = "B".$record['box_order'];
						$sql="update llx_boxes set box_order = '".$box_order."' where box_order = ".$record['box_order'];
						$resql = $db->query($sql);
					}
				}
			}
		}
	}
	$db->free($resql);
}


/*
 * Boites disponibles
 */
print "<br>\n";
print_titre($langs->trans("BoxesAvailable"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="300">'.$langs->trans("Box").'</td>';
print '<td>'.$langs->trans("Note").'/'.$langs->trans("Parameters").'</td>';
print '<td>'.$langs->trans("SourceFile").'</td>';
print '<td align="center" width="160">'.$langs->trans("ActivateOn").'</td>';
print "</tr>\n";

$sql = "SELECT rowid, file, note, tms";
$sql.= " FROM ".MAIN_DB_PREFIX."boxes_def";
$sql.= " WHERE entity = ".$conf->entity;
$resql = $db->query($sql);
$var=True;

if ($resql)
{
	$html=new Form($db);
	
	$num = $db->num_rows($resql);
	$i = 0;
	
	// Boucle sur toutes les boites
	while ($i < $num)
	{
		$obj = $db->fetch_object($resql);
	
		$module=preg_replace('/.php$/i','',$obj->file);
		include_once(DOL_DOCUMENT_ROOT."/includes/boxes/".$module.".php");
	
		$box=new $module($db,$obj->note);
	
//		if (in_array($obj->rowid, $actives) && $box->box_multiple <> 1)
		if (in_array($obj->rowid, $actives))
		{
			// La boite est deja activee
		}
		else
		{
			$var = ! $var;
	
			print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			$logo=preg_replace("/^object_/i","",$box->boximg);
			print '<tr '.$bc[$var].'>';
			print '<td>'.img_object("",$logo).' '.$box->boxlabel.'</td>';
			print '<td>' . ($obj->note?$obj->note:'&nbsp;') . '</td>';
			print '<td>' . $obj->file . '</td>';
	
			// Pour chaque position possible, on affiche un lien
			// d'activation si boite non deja active pour cette position
			print '<td align="center">';
			print $html->select_array("pos",$pos_name);
			print '<input type="hidden" name="action" value="add">';
			print '<input type="hidden" name="boxid" value="'.$obj->rowid.'">';
			print ' <input type="submit" class="button" name="button" value="'.$langs->trans("Activate").'">';
			print '</td>';
	
			print '</tr></form>';
		}
		$i++;
	}
	
	$db->free($resql);
}

print '</table>';

/*
 * Boites activ�es
 *
 */

print "<br>\n\n";
print_titre($langs->trans("BoxesActivated"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="300">'.$langs->trans("Box").'</td>';
print '<td>'.$langs->trans("Note").'/'.$langs->trans("Parameters").'</td>';
print '<td align="center" width="160">'.$langs->trans("ActiveOn").'</td>';
print '<td align="center" width="60" colspan="2">'.$langs->trans("PositionByDefault").'</td>';
print '<td align="center" width="80">'.$langs->trans("Disable").'</td>';
print "</tr>\n";

$sql = "SELECT b.rowid, b.box_id, b.position,";
$sql.= " bd.file, bd.note, bd.tms";
$sql.= " FROM ".MAIN_DB_PREFIX."boxes as b, ".MAIN_DB_PREFIX."boxes_def as bd";
$sql.= " WHERE b.box_id = bd.rowid";
$sql.= " AND bd.entity = ".$conf->entity;
$sql.= " AND b.fk_user=0";
$sql.= " ORDER by b.position, b.box_order";	// Note box_order return A01,A03...,B02,B04...

$resql = $db->query($sql);

if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;
	$var=true;
	
	$box_order=1;
	$foundrupture=1;
	
	// On lit avec un coup d'avance
	$obj = $db->fetch_object($resql);
	
	while ($obj && $i < $num)
	{
		$var = ! $var;
		$objnext = $db->fetch_object($resql);
	
		$module=preg_replace('/.php$/i','',$obj->file);
		include_once(DOL_DOCUMENT_ROOT."/includes/boxes/".$module.".php");
		$box=new $module($db,$obj->note);
	
		$logo=preg_replace("/^object_/i","",$box->boximg);
		print '<tr '.$bc[$var].'>';
		print '<td>'.img_object("",$logo).' '.$box->boxlabel.'</td>';
		print '<td>' . ($obj->note?$obj->note:'&nbsp;') . '</td>';
		print '<td align="center">' . $pos_name[$obj->position] . '</td>';
		$hasnext=true;
		$hasprevious=true;
		if ($foundrupture) { $hasprevious=false; $foundrupture=0; }
		if (! $objnext || $obj->position != $objnext->position) { $hasnext=false; $foundrupture=1; }
		print '<td align="center">'.$box_order.'</td>';
		print '<td align="center">';
		print ($hasnext?'<a href="boxes.php?action=switch&switchfrom='.$obj->rowid.'&switchto='.$objnext->rowid.'">'.img_down().'</a>&nbsp;':'');
		print ($hasprevious?'<a href="boxes.php?action=switch&switchfrom='.$obj->rowid.'&switchto='.$objprevious->rowid.'">'.img_up().'</a>':'');
		print '</td>';
		print '<td align="center">';
		print '<a href="boxes.php?rowid='.$obj->rowid.'&amp;action=delete">'.img_delete().'</a>';
		print '</td>';
	
		print "</tr>\n";
		$i++;
	
		$box_order++;
	
		if (! $foundrupture) $objprevious = $obj;
		else $box_order=1;
		$obj=$objnext;
	}
	
	$db->free($resql);
}

print '</table><br>';


# Affiche ligne d'ajout
print '<table class="noborder" width="100%">';

$var=false;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="addconst">';
print "<tr $bc[$var] class=value><td>";
print $langs->trans("MaxNbOfLinesForBoxes")."</td>\n";
print '<td>';
print '<input type="text" class="flat" size="6" name="MAIN_BOXES_MAXLINES" value="'.$conf->global->MAIN_BOXES_MAXLINES.'">';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'" name="Button"><br>';
print "</td>\n";
print '</tr>';
print '</form>';

print '</table>';


$db->close();

llxFooter('$Date$ - $Revision$');
?>
