#!/usr/bin/php
<?PHP
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       scripts/emailings/mailing-send.php
 *      \ingroup    mailing
 *      \brief      Script d'envoi d'un mailing prepare et valide
 *		\version	$Id$
 */


$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=str_replace($script_file,'',$_SERVER["PHP_SELF"]);
$path=preg_replace('@[\\\/]+$@','',$path).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
    exit;
}

if (! isset($argv[1]) || ! $argv[1]) {
	print "Usage: ".$script_file." ID_MAILING\n";
	exit;
}
$id=$argv[1];

require_once ($path."../../htdocs/master.inc.php");
require_once (DOL_DOCUMENT_ROOT."/lib/CMailFile.class.php");


$error = 0;


// We read data of email
$sql = "SELECT m.rowid, m.titre, m.sujet, m.body,";
$sql.= " m.email_from, m.email_replyto, m.email_errorsto";
$sql.= " FROM ".MAIN_DB_PREFIX."mailing as m";
$sql.= " WHERE m.statut >= 1";
$sql.= " AND m.rowid= ".$id;
$sql.= " LIMIT 1";

$resql=$db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;

	if ($num == 1)
	{
		$obj = $db->fetch_object($resql);

		dol_syslog("mailing ".$id);

		$id       = $obj->rowid;
		$subject  = $obj->sujet;
		$message  = $obj->body;
		$from     = $obj->email_from;
		$replyto  = $obj->email_replyto;
		$errorsto = $obj->email_errorsto;

		$msgishtml=-1;

		$i++;
	}
	else
	{
		$mesg="Emailing with id ".$id." not found";
		print $mesg."\n";
		dol_syslog($mesg,LOG_ERR);
	}
}


$nbok=0; $nbko=0;

// On choisit les mails non deja envoyes pour ce mailing (statut=0)
// ou envoyes en erreur (statut=-1)
$sql = "SELECT mc.rowid, mc.nom, mc.prenom, mc.email, mc.other";
$sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
$sql .= " WHERE mc.statut < 1 AND mc.fk_mailing = ".$id;

$resql=$db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);

	if ($num)
	{
		dol_syslog("nb of targets = ".$num, LOG_DEBUG);

		// Positionne date debut envoi
		$sql="UPDATE ".MAIN_DB_PREFIX."mailing SET date_envoi=SYSDATE() WHERE rowid=".$id;
		$resql2=$db->query($sql);
		if (! $resql2)
		{
			dol_print_error($db);
		}

		// Boucle sur chaque adresse et envoie le mail
		$i = 0;
		while ($i < $num)
		{
			$res=1;

			$obj = $db->fetch_object($resql);

			// sendto en RFC2822
			$sendto = str_replace(',',' ',$obj->prenom." ".$obj->nom) ." <".$obj->email.">";

			// Make subtsitutions on topic and body
			$other=explode(';',$obj->other);
			$other1=$other[0];
			$other2=$other[1];
			$other3=$other[2];
			$other4=$other[3];
			$other5=$other[4];
			$substitutionarray=array(
				'__ID__' => $obj->rowid,
				'__EMAIL__' => $obj->email,
				'__LASTNAME__' => $obj->nom,
				'__FIRSTNAME__' => $obj->prenom,
				'__OTHER1__' => $other1,
				'__OTHER2__' => $other2,
				'__OTHER3__' => $other3,
				'__OTHER4__' => $other4,
				'__OTHER5__' => $other5
			);

			$substitutionisok=true;
			$newsubject=make_substitutions($subject,$substitutionarray,$langs);
			$newmessage=make_substitutions($message,$substitutionarray,$langs);

			// Fabrication du mail
			$mail = new CMailFile($newsubject, $sendto, $from, $newmessage,
			array(), array(), array(),
            						'', '', 0, $msgishtml, $errorsto);

			if ($mail->error)
			{
				$res=0;
			}
			if (! $substitutionisok)
			{
				$mail->error='Some substitution failed';
				$res=0;
			}

			// Send Email
			if ($res)
			{
				$res=$mail->sendfile();
			}

			if ($res)
			{
				// Mail successful
				$nbok++;

				dol_syslog("ok for #".$i.($mail->error?' - '.$mail->error:''), LOG_DEBUG);

				$sql="UPDATE ".MAIN_DB_PREFIX."mailing_cibles";
				$sql.=" SET statut=1, date_envoi=SYSDATE() WHERE rowid=".$obj->rowid;
				$resql2=$db->query($sql);
				if (! $resql2)
				{
					dol_print_error($db);
				}
			}
			else
			{
				// Mail failed
				$nbko++;

				dol_syslog("error for #".$i.($mail->error?' - '.$mail->error:''), LOG_DEBUG);

				$sql="UPDATE ".MAIN_DB_PREFIX."mailing_cibles";
				$sql.=" SET statut=-1, date_envoi=SYSDATE() WHERE rowid=".$obj->rowid;
				$resql2=$db->query($sql);
				if (! $resql2)
				{
					dol_print_error($db);
				}
			}

			$i++;
		}
	}

	// Loop finished, set global statut of mail
	$statut=2;
	if (! $nbko) $statut=3;

	$sql="UPDATE ".MAIN_DB_PREFIX."mailing SET statut=".$statut." WHERE rowid=".$id;
	dol_syslog("update global status sql=".$sql, LOG_DEBUG);
	$resql2=$db->query($sql);
	if (! $resql2)
	{
		dol_print_error($db);
	}
}
else
{
	dol_print_error($db);
}

?>
