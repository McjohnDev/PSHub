<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chaines francaises pour theme_pslanguagehub. Langue par defaut du site.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PS Language Hub';
$string['choosereadme'] = "Le thème PS Language Hub. Un thème enfant de Boost qui porte la marque : le navy pour la structure, le bleu pour l'interaction, et le dégradé signature réservé aux séances en direct.";

// La phrase sous le nom de marque. Elle dit la seule chose qui distingue
// cette plateforme de tous les autres sites de langues : il n'y a pas de
// vidéothèque, il y a quelqu'un qui vous attend.
$string['brand_claim'] = 'Chaque cours est en direct. 50 minutes, un professeur.';

// Devise.
$string['tagline_learn'] = 'LEARN.';
$string['tagline_speak'] = 'SPEAK.';
$string['tagline_succeed'] = 'SUCCEED.';

// La coque publique : barre de navigation.
$string['skipmain'] = 'Aller au contenu';
$string['nav_main'] = 'Principal';
$string['nav_catalogue'] = 'Catalogue';
$string['nav_howitworks'] = 'Comment ça marche';
$string['nav_bandwidth'] = 'Faible débit';
$string['nav_app'] = 'Application';
$string['nav_signin'] = 'Se connecter';
$string['nav_signup'] = 'Créer un compte';
$string['nav_myspace'] = 'Mes cours';

// La coque publique : pied de page.
$string['foot_blurb'] = 'Cours de langues 100 % en direct, pensés pour l\'Afrique francophone.';
$string['foot_platform'] = 'Plateforme';
$string['foot_legal'] = 'Légal';
$string['foot_app'] = 'Application';
$string['foot_terms'] = 'Conditions d\'utilisation — bientôt';
$string['foot_privacy'] = 'Confidentialité des données — bientôt';
$string['foot_refunds'] = 'Politique de remboursement — bientôt';
$string['app_google_soon'] = 'Google Play — bientôt';
$string['app_apple_soon'] = 'App Store — bientôt';
// Guillemets simples : {$a} doit arriver intact dans get_string(), et PHP le
// mangerait dans une chaîne à guillemets doubles.
$string['foot_copyright'] = '© {$a} PS Language Hub — Douala, Cameroun';
$string['foot_staff'] = 'Accès enseignants et administration';

// L'illustration de session en direct sur la page de connexion. Décorative et
// masquée aux lecteurs d'écran, mais traduite : elle est bien à l'écran, et un
// visiteur francophone ne doit pas y trouver un libellé en anglais.
//
// Ces chaînes vivent dans le THÈME plutôt que d'être empruntées à local_pshub :
// la page de connexion continue ainsi de s'afficher même si le plugin venait à
// manquer — une chaîne absente ici serait une erreur fatale sur la seule page
// que personne ne peut contourner.
$string['demo_course'] = 'Anglais B1 — Conversation';
$string['demo_live'] = 'EN DIRECT';
$string['demo_teacher'] = 'Votre professeur';
$string['demo_you'] = 'Vous';
$string['demo_board'] = 'Tableau blanc partagé';

// Panneau de connexion.
$string['login_headline'] = 'Apprenez les langues, en direct.';
$string['login_lede'] = "Des cours 100 % live avec de vrais enseignants : visioconférence, tableau blanc collaboratif et suivi personnalisé.";
$string['login_point_live'] = 'Sessions live de 50 minutes maximum';
$string['login_point_teacher'] = 'Un vrai professeur qui vous répond';
$string['login_point_bandwidth'] = 'Pensé pour les connexions 2G/3G faibles';
$string['login_point_xp'] = 'XP, compétences et certificats';
$string['login_back_home'] = "Retour à l'accueil";

// La page d'inscription passe par la même mise en page ; seul le texte du
// panneau change.
$string['signup_headline'] = 'Créez votre compte apprenant';
$string['signup_lede'] = "Inscrivez-vous en quelques secondes, choisissez votre langue et rejoignez votre première session en direct.";
$string['signup_payment_note'] = 'Paiement simple par MTN Mobile Money ou Orange Money, en FCFA.';

// The learner shell: sidebar and top bar.
$string['nav_progress'] = 'Ma progression';
$string['nav_explore'] = 'Explorer les cours';
$string['nav_agenda'] = 'Mon agenda';
$string['nav_notifications'] = 'Mes notifications';
$string['nav_section_main'] = 'MENU PRINCIPAL';
$string['nav_logout'] = 'Déconnexion';
$string['role_learner'] = 'Apprenant·e';
$string['search_placeholder_app'] = 'Rechercher un cours, une langue…';

// -- The staff spaces ---------------------------------------------------------
$string['nav_section_teacher'] = 'ESPACE ENSEIGNANT';
$string['nav_section_admin'] = 'ADMINISTRATION';
$string['nav_overview'] = "Vue d'ensemble";
$string['nav_types'] = 'Types & badges';
$string['role_teacher'] = 'Enseignant·e';
$string['role_admin'] = 'Administration';
$string['action_open_moodle'] = 'Ouvrir Moodle';
$string['moodlenote_title'] = 'Interface Moodle';
$string['moodlenote_teacher'] = 'La création des cours et le détail des activités se font dans Moodle.';
$string['moodlenote_admin'] = 'La configuration détaillée (rôles, cohortes, plans de compétences) se fait dans Moodle.';
