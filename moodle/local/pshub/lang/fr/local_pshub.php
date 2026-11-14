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
 * Chaines francaises pour local_pshub. Langue par defaut de la plateforme.
 *
 * Les chaines contenant une apostrophe sont ecrites entre guillemets doubles
 * plutot qu'echappees, ce qui evite une classe entiere d'erreurs de syntaxe.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PS Language Hub';

// Capacites.
$string['pshub:viewteacherportal'] = "Accéder à l'espace enseignant";
$string['pshub:submitcourse'] = 'Soumettre un cours à la validation';
$string['pshub:schedulesession'] = 'Planifier une séance en direct';
$string['pshub:viewadminportal'] = "Accéder à l'espace d'administration";
$string['pshub:publishcourse'] = 'Valider ou refuser un cours soumis';
$string['pshub:validatepayment'] = "Valider un paiement et ouvrir l'accès";
$string['pshub:managecoursetypes'] = 'Gérer les types de cours et les étiquettes';
$string['pshub:manageteachers'] = 'Gérer les enseignants et leurs objectifs';
$string['pshub:managexprules'] = 'Gérer le barème des XP';
$string['pshub:viewaccounting'] = 'Consulter les revenus et les transactions';
$string['pshub:grantaccess'] = "Accorder manuellement l'accès à un cours";
$string['pshub:manageadmins'] = 'Créer et gérer les comptes administrateurs';

// Roles.
$string['role_pshub_admin'] = 'Administrateur principal';
$string['role_pshub_admin_desc'] = 'Valide les cours et les paiements, fixe les tarifs et les types de cours, assigne les compétences aux enseignants, configure les XP et les certificats.';
$string['role_pshub_teacher'] = 'Enseignant';
$string['role_pshub_teacher_desc'] = 'Crée des cours selon les exigences assignées, planifie et anime les séances en direct, soumet ses cours à la validation. Ne peut pas publier.';

// Messages de refus. L'apprenant ne voit jamais l'identifiant, seulement ceci.
$string['denied_payment_required'] = 'Ce cours est payant. Choisissez un moyen de paiement pour continuer.';
$string['denied_payment_pending'] = "Paiement reçu. Nous le vérifions, en général sous une heure. Vous serez prévenu dès l'ouverture du cours.";
$string['denied_not_available'] = "Ce cours n'est pas disponible pour le moment.";
$string['denied_access_expired'] = 'Votre accès à ce cours est arrivé à échéance.';

// Journal d'audit.
$string['audit_course_submitted'] = 'Cours soumis à la validation';
$string['audit_course_published'] = 'Cours validé';
$string['audit_course_rejected'] = 'Cours refusé';
$string['audit_course_archived'] = 'Cours archivé';
$string['audit_payment_received'] = 'Paiement reçu, en attente de validation';
$string['audit_payment_validated'] = 'Paiement validé, accès ouvert';
$string['audit_payment_rejected'] = 'Paiement refusé';
$string['audit_access_granted'] = 'Accès accordé manuellement';
$string['audit_access_revoked'] = 'Accès retiré';

// Confidentialite : objet de chaque table.
$string['privacy:metadata:userprofile'] = "Préférences de profil d'un apprenant.";
$string['privacy:metadata:access'] = "Les cours et les packs qu'un apprenant peut ouvrir, et à quel titre.";
$string['privacy:metadata:transaction'] = 'Les paiements effectués par un apprenant et leur validation. Conservés comme pièces comptables et dissociés plutôt que supprimés sur demande.';
$string['privacy:metadata:session'] = 'Les séances en direct planifiées par un enseignant.';
$string['privacy:metadata:attendance'] = 'La présence aux séances en direct.';
$string['privacy:metadata:assign'] = 'Les objectifs de compétences assignés à un enseignant.';
$string['privacy:metadata:xp'] = "Les points d'expérience gagnés par un apprenant.";
$string['privacy:metadata:learner'] = "Un résumé de la progression d'un apprenant, dérivé du registre de points.";
$string['privacy:metadata:groupmember'] = "L'appartenance à un groupe d'apprentissage.";
$string['privacy:metadata:pushtoken'] = 'Les identifiants des appareils utilisés pour les rappels de séance.';
$string['privacy:metadata:audit'] = 'Le registre de qui a validé les cours et les paiements. Conservé à des fins de responsabilité et dissocié plutôt que supprimé sur demande.';

// Confidentialite : champs.
$string['privacy:metadata:userprofile:userid'] = 'La personne à qui appartient ce profil.';
$string['privacy:metadata:userprofile:phone'] = 'Le numéro utilisé pour les paiements Mobile Money.';
$string['privacy:metadata:userprofile:lang'] = "Langue d'interface préférée.";
$string['privacy:metadata:userprofile:audience'] = 'Si le catalogue affiché est celui des adultes ou des enfants.';

$string['privacy:metadata:access:userid'] = "La personne détenant le droit d'accès.";
$string['privacy:metadata:access:target'] = 'Le cours ou le pack concerné.';
$string['privacy:metadata:access:source'] = "Si l'accès est automatique, payé ou accordé manuellement.";
$string['privacy:metadata:access:status'] = 'Si le droit est actif, échu ou retiré.';
$string['privacy:metadata:access:reason'] = "La raison enregistrée lors d'un accès accordé manuellement.";

$string['privacy:metadata:transaction:userid'] = 'La personne qui a payé.';
$string['privacy:metadata:transaction:amount'] = 'Le montant payé.';
$string['privacy:metadata:transaction:method'] = 'Le moyen de paiement utilisé.';
$string['privacy:metadata:transaction:msisdn'] = 'Le numéro qui a payé.';
$string['privacy:metadata:transaction:status'] = 'Où en est le paiement dans la validation.';

$string['privacy:metadata:session:teacherid'] = "L'enseignant qui anime la séance.";
$string['privacy:metadata:session:title'] = 'Le titre de la séance.';
$string['privacy:metadata:session:start'] = 'Le début prévu de la séance.';

$string['privacy:metadata:attendance:userid'] = 'Le participant.';
$string['privacy:metadata:attendance:joined'] = "L'heure d'arrivée.";
$string['privacy:metadata:attendance:duration'] = 'La durée de présence.';
$string['privacy:metadata:attendance:present'] = 'Si cela compte comme une présence.';

$string['privacy:metadata:assign:teacherid'] = "L'enseignant à qui l'objectif est assigné.";
$string['privacy:metadata:assign:brief'] = "Ce que l'enseignant doit couvrir.";
$string['privacy:metadata:assign:status'] = "L'avancement par rapport à l'objectif.";

$string['privacy:metadata:xp:userid'] = "L'apprenant qui a gagné les points.";
$string['privacy:metadata:xp:event'] = 'Ce pour quoi les points ont été gagnés.';
$string['privacy:metadata:xp:points'] = 'Le nombre de points attribués.';

$string['privacy:metadata:learner:userid'] = "L'apprenant décrit par ce résumé.";
$string['privacy:metadata:learner:xp'] = 'Le total des points gagnés.';
$string['privacy:metadata:learner:streak'] = "Jours consécutifs d'activité.";

$string['privacy:metadata:groupmember:userid'] = 'Le membre du groupe.';
$string['privacy:metadata:groupmember:joined'] = "La date d'entrée dans le groupe.";

$string['privacy:metadata:pushtoken:userid'] = "Le propriétaire de l'appareil.";
$string['privacy:metadata:pushtoken:token'] = "L'identifiant de l'appareil servant à délivrer les notifications.";
$string['privacy:metadata:pushtoken:platform'] = "La plateforme de l'appareil.";

$string['privacy:metadata:audit:actorid'] = "La personne qui a effectué l'action.";
$string['privacy:metadata:audit:action'] = 'Ce qui a été fait.';
$string['privacy:metadata:audit:ip'] = "L'adresse depuis laquelle l'action a été effectuée.";

// Reglages.
$string['settings_sessions'] = 'Séances en direct';
$string['settings_sessioncap'] = "Durée maximale d'une séance";
$string['settings_sessioncap_desc'] = "La limite stricte d'une séance en direct. Un enseignant peut planifier plus court, jamais plus long. BigBlueButton l'applique côté serveur, donc elle tient même si tous les clients sont déconnectés.";
$string['settings_presencethreshold'] = 'Seuil de présence (%)';
$string['settings_presencethreshold_desc'] = "Part de la durée prévue qu'un apprenant doit suivre pour que la séance compte comme suivie. Quatre-vingt-dix secondes sur cinquante minutes ne sont pas une présence.";
$string['settings_monetisation'] = 'Monétisation';
$string['settings_packsenabled'] = 'Activer les packs de cours';
$string['settings_packsenabled_desc'] = "Désactivé, un droit d'accès de niveau pack n'ouvre rien, même s'il existe. À laisser désactivé en phase 1.";
$string['settings_autovalidate'] = 'Valider automatiquement les paiements {$a}';
$string['settings_autovalidate_desc'] = "Activé, un rappel vérifié ouvre l'accès sans qu'un administrateur le contrôle. À laisser désactivé tant que le rapprochement de ce moyen de paiement n'est pas éprouvé.";

// Moyens de paiement.
$string['method_mtn_momo'] = 'MTN Mobile Money';
$string['method_orange_money'] = 'Orange Money';
$string['method_wave'] = 'Wave';
$string['method_card'] = 'Carte bancaire';
$string['method_bank_transfer'] = 'Virement bancaire';
$string['method_cash'] = 'Espèces';

// Erreurs.
$string['cannotenrol'] = "Ce cours n'a pas pu être ouvert. Veuillez réessayer.";

// Administration des types de cours (AC-8).
$string['coursetypes'] = 'Types de cours et étiquettes';
$string['coursetypes_intro'] = "Les types décident de l'étiquette portée par un cours et de son tarif. En ajouter un ici prend effet immédiatement, sans déploiement.";
$string['type_add'] = 'Ajouter un type de cours';
$string['type_edit'] = 'Modifier le type de cours';
$string['type_none'] = "Aucun type de cours pour l'instant. Ajoutez-en un pour commencer à étiqueter le catalogue.";
$string['type_created'] = 'Type de cours créé.';
$string['type_updated'] = 'Type de cours modifié.';
$string['type_deleted'] = 'Type de cours supprimé.';
$string['type_delete_confirm'] = 'Supprimer ce type de cours ?';
// Note the escaped \$a. Double quotes spare the apostrophe in "d'abord" an
// escape, but they also mean PHP would interpolate {$a} as a variable, emitting
// an empty placeholder and an "Undefined variable" warning that becomes a 500
// on any page loading this file. Moodle's {$a} must reach get_string()
// literally: use single quotes, or escape the dollar as here.
$string['type_delete_inuse'] = "Ce type ne peut pas être supprimé : il est attaché à {\$a} cours. Détachez-le d'abord, ou désactivez-le.";
$string['type_badge'] = 'Étiquette';
$string['type_code'] = 'Code';
$string['type_code_help'] = "L'identifiant stable utilisé par la plateforme, par exemple promo20 ou hot. Lettres, chiffres, tiret et tiret bas. Le modifier ensuite casse tout ce qui référence l'ancienne valeur : choisissez une fois.";
$string['type_code_taken'] = 'Un autre type de cours utilise déjà ce code.';
$string['type_name_fr'] = 'Nom (français)';
$string['type_name_en'] = 'Nom (anglais)';
$string['type_name_en_help'] = "Facultatif. Le français est la langue par défaut de la plateforme : un nom anglais vide reprend simplement le nom français.";
$string['type_kind'] = 'Nature';
$string['type_kind_help'] = "Ce que fait le type. Seule une remise change le tarif ; mise en avant, examen et personnalisé sont purement visuels. Gratuit ouvre l'accès sans paiement.";
$string['type_discount'] = 'Remise (%)';
$string['type_discount_range'] = 'Une remise doit être comprise entre 1 et 100.';
$string['type_grantsfree'] = "Ouvre l'accès gratuitement";
$string['type_grantsfree_label'] = 'Ce type rend le cours gratuit';
$string['type_grantsfree_help'] = "L'étiquette devient le tarif. Un cours portant ce type s'ouvre sans paiement même si un prix y figure encore, ce qui évite d'avoir à modifier deux choses.";
$string['type_free_vs_discount'] = 'Un type ne peut pas être à la fois gratuit et une remise. Gratuit met déjà le tarif à zéro.';
$string['type_badgestyle'] = "Couleur de l'étiquette";
$string['type_sortorder'] = 'Ordre';
$string['type_sortorder_help'] = "Les plus petits nombres apparaissent en premier lorsqu'un cours porte plusieurs étiquettes.";
$string['type_active'] = 'Actif';
$string['type_active_label'] = "Disponible à l'usage";
$string['type_active_help'] = "Désactiver un type cesse d'agir sur les tarifs et de l'afficher, sans avoir à le détacher de chaque cours.";
$string['type_effect'] = 'Effet';
$string['type_effect_free'] = 'Cours gratuit';
$string['type_effect_discount'] = '{$a} % de remise';
$string['type_effect_none'] = 'Visuel uniquement';
$string['type_inuse'] = 'Cours';
$string['kind_discount'] = 'Remise';
$string['kind_highlight'] = 'Mise en avant';
$string['kind_free'] = 'Gratuit';
$string['kind_exam'] = 'Examen';
$string['kind_custom'] = 'Personnalisé';
$string['style_promo'] = 'Rose (promotion)';
$string['style_hot'] = 'Orange (populaire)';
$string['style_free'] = 'Bleu (gratuit)';
$string['style_exam'] = 'Contour (neutre)';

// Catalogue public (S3).
$string['catalogue'] = 'Cours';
$string['catalogue_count'] = '{$a} cours';
$string['catalogue_empty'] = "Aucun cours ne correspond à ces filtres pour l'instant. Essayez de les élargir.";
$string['filter_search'] = 'Rechercher';
$string['filter_search_placeholder'] = 'Titre du cours';
$string['filter_language'] = 'Langue';
$string['filter_level'] = 'Niveau';
$string['filter_access'] = 'Accès';
$string['filter_access_free'] = 'Gratuit';
$string['filter_access_paid'] = 'Payant';
$string['filter_any'] = 'Tous';
$string['filter_apply'] = 'Filtrer';
$string['filter_clear'] = 'Effacer les filtres';
$string['card_free'] = 'Gratuit';
$string['card_duration'] = '{$a} min par séance';
$string['card_onetoone'] = 'En tête-à-tête';
$string['cta_signin_to_enrol'] = 'Se connecter pour rejoindre ce cours';
$string['cta_browse_freely'] = "Parcourir le catalogue ne demande aucun compte. Vous vous connectez uniquement pour rejoindre un cours.";
$string['cta_open_course'] = 'Ouvrir le cours';
$string['cta_enrol'] = 'Rejoindre ce cours';
$string['back_to_catalogue'] = 'Retour à tous les cours';
$string['card_view'] = 'Voir le cours';
$string['catalogue_lede'] = 'Tous les cours sont dispensés en direct par nos enseignants — choisissez le vôtre.';
$string['filter_type'] = 'Type';
$string['breadcrumb'] = 'Vous êtes ici';
$string['course_about'] = 'À propos de ce cours';
// Guillemets simples : {$a} doit arriver intact dans get_string().
$string['course_included_live'] = 'Des sessions en direct de {$a} minutes avec un professeur';
$string['course_included_board'] = 'Tableau blanc partagé et supports de session';
$string['course_included_group'] = 'Un petit groupe, pour avoir le temps de parler';
$string['course_included_onetoone'] = 'En tête-à-tête avec votre professeur';
$string['course_pay_with'] = 'Payez par';
$string['course_pay_card'] = 'Carte bancaire';
$string['course_pay_note'] = "L'accès au cours est débloqué dès la validation de votre paiement.";

// -- La page d'accueil publique -----------------------------------------------
// Chaque promesse faite ici en est une que la plateforme tient. Un visiteur de
// Douala saura en une semaine si c'etait vrai : les liens vers les magasins
// d'applications disent donc « bientôt » au lieu de faire semblant, et aucun
// chiffre n'apparaît que le produit ne tienne pas.
//
// Guillemets simples partout où une chaîne contient {$a} : PHP interpolerait
// le placeholder dans une chaîne à guillemets doubles.

$string['home_title'] = 'Apprendre une langue en direct';
$string['home_hero_title'] = 'Apprenez une langue en direct, avec de vrais professeurs.';
$string['home_hero_lede'] = "Des cours 100 % live : visioconférence, tableau blanc collaboratif et suivi personnalisé — conçus pour rester fluides même en connexion 2G/3G.";
$string['home_hero_cta'] = 'Découvrir les cours';
$string['home_pill_live'] = 'Sessions live de 50 min';
$string['home_pill_momo'] = 'Paiement Mobile Money';
$string['home_pill_certificate'] = 'Certificats vérifiables par QR';

// L'illustration de session dans le bandeau. Décorative et masquée aux
// lecteurs d'écran, mais traduite quand même : elle est bien à l'écran.
$string['home_demo_course'] = 'Anglais B1 — Conversation';
$string['home_demo_live'] = 'EN DIRECT';
$string['home_demo_teacher'] = 'Votre professeur';
$string['home_demo_you'] = 'Vous';
$string['home_demo_phrase'] = 'In my opinion…';
$string['home_demo_variants'] = 'I think · I believe · From my point of view';
$string['home_demo_board'] = 'Tableau blanc partagé';
$string['home_demo_join'] = 'Rejoindre';

$string['home_featured_title'] = 'Cours mis en avant';
$string['home_featured_lede'] = "Sélectionnés par l'équipe pédagogique.";
$string['home_featured_all'] = 'Voir tout le catalogue';

$string['home_steps_title'] = 'Comment ça marche';
$string['home_step1_title'] = 'Créez votre compte';
$string['home_step1_text'] = 'Gratuit, et cela prend deux minutes.';
$string['home_step2_title'] = 'Choisissez un cours et payez';
$string['home_step2_text'] = "MTN MoMo, Orange Money ou carte bancaire. L'accès s'ouvre dès validation du paiement.";
$string['home_step3_title'] = 'Rejoignez vos sessions live';
$string['home_step3_text'] = 'Visioconférence, tableau blanc et documents partagés, avec un rappel avant chaque session.';

$string['home_network_title'] = "Conçu pour les réseaux d'Afrique";
$string['home_network_lede'] = 'Une session live reste utilisable dès 64 kbit/s ; la visio complète à partir de 300 kbit/s environ.';
$string['home_network_audio_title'] = 'Mode audio seul, automatique';
$string['home_network_audio_text'] = 'Quand le débit chute, la vidéo se coupe : la voix et le tableau blanc continuent.';
$string['home_network_board_title'] = 'Un tableau blanc vectoriel';
$string['home_network_board_text'] = 'Les tracés et les documents circulent en données légères, jamais en flux vidéo.';
$string['home_network_reconnect_title'] = 'Reconnexion sans perdre sa place';
$string['home_network_reconnect_text'] = 'Une microcoupure du réseau ne vous fait pas quitter la session.';

$string['home_app_title'] = 'Emportez vos cours partout';
$string['home_app_point_size'] = 'Une application légère, moins de 25 Mo';
$string['home_app_point_offline'] = 'Téléchargement des supports en Wi-Fi, consultation hors ligne';
$string['home_app_point_reminders'] = 'Rappels de session 24 h, 1 h et 10 min avant le direct';

$string['home_certificate_title'] = 'XP, compétences et certificats';
$string['home_certificate_text'] = "Gagnez des XP à chaque session, validez vos compétences et recevez un certificat que n'importe qui peut vérifier par QR code.";
$string['home_teachers_title'] = 'Un professeur, pas une vidéothèque';
$string['home_teachers_text'] = "Chaque cours est donné en direct par quelqu'un qui vous répond. Il n'y a pas de bibliothèque d'enregistrements à parcourir seul.";

$string['home_faq_title'] = 'Questions fréquentes';
$string['home_faq_q_live'] = 'Les cours sont-ils vraiment en direct ?';
$string['home_faq_a_live'] = "Oui, tous. Une session dure 50 minutes avec un professeur, en petit groupe ou en tête-à-tête. Rien sur cette plateforme n'est une vidéo enregistrée à l'avance.";
$string['home_faq_q_pay'] = 'Comment payer ?';
$string['home_faq_a_pay'] = "MTN Mobile Money, Orange Money ou carte bancaire. Le cours s'ouvre dès que le paiement est validé.";
$string['home_faq_q_missed'] = 'Et si je manque une session ?';
$string['home_faq_a_missed'] = 'Les supports restent dans votre cours et votre professeur peut vous dire ce qui a été vu.';
$string['home_faq_q_data'] = 'Combien de données consomme une session ?';
$string['home_faq_a_data'] = "Une session fonctionne dès 64 kbit/s en audio et tableau blanc. La vidéo demande environ 300 kbit/s et se coupe d'elle-même quand la connexion ne suit plus.";
$string['home_faq_cta'] = 'Créer un compte gratuit';

// Le champ de profil personnalisé qui capte le numéro Mobile Money à
// l'inscription. Moodle le stocke une fois, en texte, dans la langue du site.
$string['profilecategory'] = 'Contact';
// The name this category was first created with, kept only so the upgrade
// step can recognise and rename it. Do not use it anywhere else.
$string['profilecategory_legacy'] = 'PS Language Hub';
$string['profilefield_phone'] = 'Numéro de téléphone';
$string['profilefield_phone_help'] = "Votre numéro Mobile Money, pour payer un cours. Vous pourrez l'ajouter plus tard.";

// -- The learner's own space --------------------------------------------------
// Single quotes where a string carries {$a}: PHP would eat the placeholder
// inside double quotes.
$string['learn_title'] = 'Ma progression';
$string['learn_greeting'] = 'Bonjour, {$a}';
$string['learn_tile_courses'] = 'Cours en cours';
$string['learn_tile_sessions'] = 'Sessions live à venir';
$string['learn_tile_xp'] = 'XP gagnés';
$string['learn_courses_title'] = 'Mes cours';
$string['learn_find_courses'] = 'Trouver un autre cours';
$string['learn_no_courses'] = "Vous n'avez pas encore rejoint de cours. Ici, chaque cours est donné en direct, par un professeur, en petit groupe.";
$string['learn_browse'] = 'Parcourir les cours';
$string['learn_sessions_title'] = 'À venir';
$string['learn_no_sessions'] = "Aucune session live n'est encore programmée. Votre professeur planifiera les prochaines, et vous serez prévenu 24 h, 1 h et 10 min avant chacune.";
$string['learn_open_course'] = 'Ouvrir';
$string['catalogue_explore'] = 'Explorer les cours';

// -- The learner's own course page --------------------------------------------
// Single quotes where a string carries {$a}.
$string['learn_crumb_detail'] = 'Détail du cours';
$string['learn_course_level'] = 'Niveau {$a}';
$string['learn_course_duration'] = '{$a} min maximum par séance';
$string['learn_course_group'] = '{$a} apprenants par groupe maximum';
$string['learn_course_onetoone'] = 'Cours en tête-à-tête';
$string['learn_course_teacher'] = 'Avec {$a}';
$string['learn_course_teacher_pending'] = "L'enseignant de ce cours n'est pas encore désigné.";
$string['learn_course_content'] = 'Contenu du cours';
$string['learn_course_nocontent'] = "Ce cours n'a pas encore de ressource en ligne : tout s'y passe en direct, avec votre professeur.";
$string['learn_group_resources'] = 'Ressources';
$string['learn_group_quizzes'] = 'Quiz';
$string['learn_group_assignments'] = 'Devoirs';
$string['learn_group_workshops'] = 'Ateliers';
$string['learn_group_discussions'] = 'Échanges';
$string['learn_progress_title'] = 'Ma progression';
$string['learn_progress_sessions'] = '{$a->attended} séances sur {$a->total}';
$string['learn_progress_percent'] = '{$a} %';
$string['learn_live_title'] = 'Sessions live';
$string['learn_live_now'] = 'En direct';
$string['learn_live_join'] = 'Rejoindre la session live';
$string['learn_session_attended'] = 'Séance suivie';
$string['learn_session_past'] = 'Séance passée';
$string['learn_session_upcoming'] = 'À venir';
$string['learn_session_room_pending'] = 'La salle de cours ouvrira peu avant le début.';
$string['learn_course_nosessions'] = "Aucune séance n'est encore programmée sur ce cours. Votre professeur planifiera les prochaines, et vous serez prévenu 24 h, 1 h et 10 min avant chacune.";
$string['learn_course_unavailable'] = "Ce cours n'existe pas, ou il n'est pas publié.";

// -- The teacher's console ----------------------------------------------------
// Single quotes where a string carries {$a}.
$string['status_draft'] = 'Brouillon';
$string['status_pending'] = 'En validation';
$string['status_published'] = 'Publié';
$string['status_rejected'] = 'Rejeté';
$string['status_archived'] = 'Archivé';
$string['teacher_title'] = "Espace enseignant";
$string['teacher_greeting'] = 'Bonjour, {$a}';
$string['teacher_lede'] = "Voici l'état de vos cours et vos prochaines séances.";
$string['teacher_tile_published'] = 'Cours publiés';
$string['teacher_tile_pending'] = 'En attente de validation';
$string['teacher_tile_learners'] = 'Apprenants inscrits';
$string['teacher_courses_title'] = 'Mes cours';
$string['teacher_create'] = 'Créer un cours dans Moodle';
$string['teacher_open'] = 'Ouvrir';
$string['teacher_learner_one'] = '{$a} apprenant·e';
$string['teacher_learner_count'] = '{$a} apprenant·e·s';
$string['teacher_no_courses'] = "Vous n'avez pas encore de cours. La création et le contenu se font dans Moodle ; le statut de validation et vos séances se suivent ici.";
$string['teacher_briefs_title'] = "Ce que l'administration vous a demandé";
$string['teacher_sessions_title'] = 'Mes prochaines séances';
$string['teacher_no_sessions'] = "Aucune séance programmée. Vos séances apparaîtront ici dès qu'elles seront planifiées.";
$string['teacher_note_reminders'] = 'Les rappels sont envoyés automatiquement 24 h, 1 h et 10 min avant chaque séance.';
$string['teacher_note_brief'] = "Vos cours doivent couvrir les compétences assignées par l'administration avant soumission.";

// -- The administration console -----------------------------------------------
// Single quotes where a string carries {$a}.
$string['admin_title'] = "Vue d'ensemble";
$string['admin_lede'] = "Ce qui attend une décision, et ce qui existe sur la plateforme.";
$string['admin_tile_pending'] = 'Cours à valider';
$string['admin_tile_published'] = 'Cours publiés';
$string['admin_tile_learners'] = 'Apprenants inscrits';
$string['admin_tile_teachers'] = 'Enseignants';
$string['admin_queue_title'] = 'Cours en attente de validation';
$string['admin_queue_empty'] = "Aucun cours n'attend de décision.";
$string['admin_queue_readonly'] = "Approuver ou rejeter se fait depuis la fiche du cours ; cette page ne modifie rien.";
$string['admin_review'] = 'Examiner';
$string['admin_types_title'] = 'Types et badges actifs';
$string['admin_types_manage'] = 'Gérer les types';
$string['admin_money_title'] = 'Paiements';
$string['admin_money_validated'] = 'Encaissé et validé';
$string['admin_money_pending'] = '{$a} paiement(s) à valider';
$string['admin_money_none'] = "Aucun paiement n'a encore été enregistré.";
$string['admin_note_access'] = "L'accès à un cours est ouvert par la validation d'un paiement, jamais à la main. Chaque validation est journalisée.";
$string['admin_note_drafts'] = '{$a} cours en brouillon ne sont pas encore soumis.';
$string['nav_admin_console'] = 'Administration PS Hub';
$string['nav_teacher_console'] = 'Espace enseignant';

// -- The course validation workflow -------------------------------------------
// Single quotes where a string carries {$a}.
$string['messageprovider:coursesubmitted'] = 'Un cours a été soumis à validation';
$string['messageprovider:coursereviewed'] = 'Décision sur un cours que vous avez soumis';
$string['review_submit'] = 'Soumettre à validation';
$string['review_approve'] = 'Approuver';
$string['review_reject'] = 'Rejeter';
$string['review_cannot_submit'] = "Ce cours ne peut pas être soumis dans son état actuel.";
$string['review_not_pending'] = "Ce cours n'attend pas de décision.";
$string['review_comment_required'] = "Un rejet doit dire pourquoi. Sans motif, l'enseignant n'apprend rien.";
$string['review_incomplete'] = 'Ce cours est incomplet : {$a}';
$string['review_problem_notahubcourse'] = "Ce cours ne fait pas partie de la plateforme.";
$string['review_problem_nosummary'] = 'il lui manque une description ;';
$string['review_problem_nolanguage'] = 'la langue enseignée n\'est pas renseignée ;';
$string['review_problem_nolevel'] = 'le niveau CECR n\'est pas renseigné ;';
$string['review_problem_noprice'] = 'il est marqué payant mais son tarif est à zéro ;';
$string['review_submitted'] = 'Cours soumis à validation.';
$string['review_approved'] = 'Cours approuvé et publié.';
$string['review_rejected'] = 'Cours rejeté, et le motif a été envoyé à son auteur.';
$string['review_reject_title'] = 'Rejeter ce cours';
$string['review_reject_lede'] = "Dites pourquoi. Ce texte part à l'enseignant : c'est sa seule indication sur ce qu'il doit corriger.";
$string['review_reject_label'] = 'Motif du rejet';
$string['review_confirm_approve'] = 'Publier « {$a} » ?';
$string['review_blocked'] = 'À compléter avant soumission : {$a}';
$string['notify_submitted_subject'] = 'Un cours attend votre validation';
$string['notify_submitted_body'] = 'Le cours « {$a} » a été soumis et attend une décision.';
$string['notify_approved_subject'] = 'Votre cours est publié';
$string['notify_approved_body'] = 'Le cours « {$a} » a été approuvé : il est désormais visible au catalogue.';
$string['notify_rejected_subject'] = "Votre cours n'a pas été retenu";
$string['notify_rejected_body'] = 'Le cours « {$a->course} » a été rejeté. Motif : {$a->comment}';
$string['admin_queue_audited'] = 'Approuver publie le cours au catalogue. Rejeter exige un motif, qui est envoyé à son auteur. Chaque décision est journalisée.';

// -- Creating and editing a hub course ----------------------------------------
// Single quotes where a string carries {$a}.
$string['course_form_create'] = 'Créer un cours';
$string['course_form_edit'] = 'Modifier le cours';
$string['course_form_lede'] = "Ce que le catalogue a besoin de savoir. Le contenu du cours s'écrit ensuite dans Moodle.";
$string['course_form_about'] = 'Le cours';
$string['course_form_title'] = 'Intitulé';
$string['course_form_summary'] = 'Description';
$string['course_form_summary_help'] = "C'est le texte que lit un visiteur avant de payer. Dites ce qu'on y apprend et à qui le cours s'adresse.";
$string['course_form_language'] = 'Langue enseignée';
$string['course_form_level'] = 'Niveau CECR';
$string['course_form_audience'] = 'Public';
$string['audience_adult'] = 'Adultes';
$string['audience_kid'] = 'Enfants';
$string['course_form_teaching'] = 'Le déroulement';
$string['course_form_duration'] = 'Durée d\'une séance (minutes)';
$string['course_form_duration_help'] = "La plateforme coupe une séance à la durée maximale configurée, quoi qu'annonce un cours. Vous pouvez prévoir plus court, jamais plus long.";
$string['course_form_duration_short'] = 'Une séance de moins de 10 minutes ne tient pas debout.';
$string['course_form_duration_long'] = 'La durée maximale est de {$a} minutes sur cette plateforme.';
$string['course_form_onetoone'] = 'Cours en tête-à-tête';
$string['course_form_maxparticipants'] = 'Apprenants par groupe (maximum)';
$string['course_form_group_small'] = "Un groupe de moins de deux personnes est un tête-à-tête : cochez plutôt la case.";
$string['course_form_price'] = 'Le tarif';
$string['course_form_access'] = 'Accès';
$string['course_form_amount'] = 'Tarif (FCFA)';
$string['course_form_amount_help'] = "Le tarif affiché au catalogue. Une remise liée à un badge s'applique par-dessus.";
$string['course_form_price_zero'] = "Un cours payant a besoin d'un tarif supérieur à zéro.";
$string['course_form_save'] = 'Enregistrer';
$string['course_form_created'] = 'Cours créé en brouillon. Ajoutez-y votre contenu dans Moodle, puis soumettez-le à validation.';
$string['course_form_updated'] = 'Cours mis à jour.';
$string['course_locked'] = "Ce cours ne peut pas être modifié : il est en cours de validation, ou déjà publié.";
$string['teacher_edit'] = 'Modifier';
$string['settings_catalogue'] = 'Catalogue';
$string['settings_languages'] = 'Langues enseignées';
$string['settings_languages_desc'] = "Les codes de langue proposés à la création d'un cours, séparés par des virgules. Ajouter une langue ici suffit : rien à déployer.";
$string['settings_category'] = 'Catégorie des cours';
$string['settings_category_desc'] = "La catégorie Moodle dans laquelle les cours de la plateforme sont créés. Laissée vide, la première catégorie visible est utilisée.";

// -- Featured courses ---------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['featured_title'] = 'Cours mis en avant';
$string['featured_lede'] = "Les cours que la page d'accueil présente en premier, dans cet ordre.";
$string['featured_current'] = 'En avant actuellement';
$string['featured_seehome'] = "Voir la page d'accueil";
$string['featured_position'] = 'Position {$a}';
$string['featured_up'] = 'Monter';
$string['featured_down'] = 'Descendre';
$string['featured_remove'] = 'Retirer';
$string['featured_none'] = "Aucun cours n'est mis en avant. La page d'accueil présente alors les cours publiés les plus récents.";
$string['featured_add'] = 'Ajouter un cours';
$string['featured_choose'] = 'Cours à mettre en avant';
$string['featured_add_action'] = 'Mettre en avant';
$string['featured_nochoices'] = 'Tous les cours publiés sont déjà mis en avant.';
$string['featured_saved'] = "Page d'accueil mise à jour.";
$string['featured_not_published'] = "Un cours non publié ne peut pas être mis en avant : le catalogue refuserait de l'afficher.";
$string['nav_featured'] = 'Mise en avant';
$string['featured_notlive'] = "Pas affiché : ce cours n'est pas publié.";

// -- Live sessions ------------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['sessions_title'] = 'Séances en direct';
$string['sessions_lede'] = "Les dates auxquelles ce cours a réellement lieu. Sans elles, personne ne peut le suivre.";
$string['sessions_add'] = 'Planifier une séance';
$string['sessions_edit'] = 'Déplacer la séance';
$string['sessions_upcoming'] = 'À venir';
$string['sessions_past'] = 'Passées';
$string['sessions_none'] = "Aucune séance programmée. Tant que cette liste est vide, le cours n'a pas de date à annoncer.";
$string['sessions_move'] = 'Déplacer';
$string['sessions_cancel'] = 'Annuler';
$string['sessions_was_cancelled'] = 'annulée';
$string['sessions_plate'] = 'LIVE';
$string['session_form_title'] = 'Intitulé de la séance';
$string['session_form_title_help'] = "Ce que les apprenants verront dans leur agenda. « Séance 3 — au restaurant » vaut mieux que « Séance 3 ».";
$string['session_form_start'] = 'Début';
$string['session_form_minutes'] = 'Durée (minutes)';
$string['session_form_caphint'] = 'La plateforme clôt toute séance à {$a} minutes. Vous pouvez prévoir plus court ; une durée plus longue sera ramenée à cette limite.';
$string['session_form_save'] = 'Enregistrer la séance';
$string['session_form_past'] = "Cette date est déjà passée. Une séance qui ne peut pas être suivie n'a pas lieu d'être planifiée.";
$string['session_scheduled'] = 'Séance planifiée.';
$string['session_rescheduled'] = 'Séance déplacée.';
$string['session_cancelled'] = 'Séance annulée. Les apprenants ne la verront plus comme un rendez-vous.';
$string['session_title_required'] = 'Une séance a besoin d\'un intitulé.';
$string['session_too_short'] = 'Une séance de moins de 10 minutes ne tient pas debout.';
$string['session_not_movable'] = "Cette séance a commencé ou s'est terminée : elle appartient à l'historique, pas au planning.";
$string['teacher_sessions_link'] = 'Séances';

// -- Walking into a session ---------------------------------------------------
$string['join_ok'] = 'Rejoindre la séance';
$string['join_unknown'] = "Cette séance n'existe pas.";
$string['join_cancelled'] = 'Cette séance a été annulée.';
$string['join_ended'] = 'Cette séance est terminée.';
$string['join_tooearly'] = "La salle ouvre 15 minutes avant le début.";
$string['join_full'] = 'Le groupe est complet.';
$string['join_noaccess'] = "Votre accès à ce cours ne couvre plus cette séance.";
$string['course_dates'] = 'Prochaines séances';
$string['session_minutes'] = '{$a} min';

// -- The session lobby --------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['lobby_here'] = 'Séance';
$string['lobby_joinnote'] = "La salle s'ouvre dans une nouvelle page. Si votre connexion faiblit, revenez ici et réessayez : votre place vous reste.";
$string['lobby_noroom'] = "La salle de cette séance n'est pas encore prête. Votre professeur la crée avant le début ; revenez dans quelques minutes.";
$string['lobby_opensat'] = 'La salle ouvre à {$a}.';
$string['lobby_servertime'] = 'Il est {$a} sur la plateforme.';
$string['lobby_backtocourse'] = 'Revenir au cours';
$string['lobby_seecourse'] = 'Voir ce cours';
$string['lobby_opensonday'] = 'La salle ouvre {$a}.';
$string['calendar_description'] = 'Séance en direct de PS Language Hub.';
$string['task_close_sessions'] = 'Clôturer les séances qui dépassent leur fin';
$string['lobby_endswarning'] = 'La séance se termine dans {$a}.';

// -- Session reminders --------------------------------------------------------
// Single quotes where a string carries {$a}. The message states WHEN the class
// starts rather than "in one hour": cron can be late, and a late reminder that
// names an hour is worse than one that names the time.
$string['task_send_reminders'] = 'Envoyer les rappels de séance';
$string['messageprovider:sessionreminder'] = 'Rappel avant une séance en direct';
$string['notify_reminder_subject'] = 'Rappel : {$a}';
$string['notify_reminder_body'] = 'Votre séance « {$a->session} » du cours « {$a->course} » commence {$a->when}.';

// -- The payment seam ---------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['payment_noprovider'] = "Aucun prestataire de paiement n'est configuré sur ce site. Les paiements sont impossibles tant que le compte marchand n'est pas ouvert.";
$string['payment_callback_unreadable'] = "Ce rappel de paiement est illisible.";
$string['fakepayments_disabled'] = "Le faux prestataire de paiement n'est pas activé. Il exige la ligne \$CFG->pshub_fake_payments = true; dans config.php, et ne doit jamais atteindre la production.";
$string['fakepayments_waiting'] = "Paiement lancé. Aucune confirmation ne viendra : ce scénario sert à vérifier ce qui se passe quand un paiement reste en suspens.";
$string['fakepayments_push'] = 'Une demande de confirmation a été envoyée au {$a}. Saisissez votre code sur le téléphone.';
$string['fakepayments_declined'] = 'Paiement refusé par le prestataire.';
$string['fakepayments_banner'] = "⚠ Ce site utilise un FAUX prestataire de paiement. Aucun paiement n'est réel et aucun argent ne change de main.";
$string['settings_payments'] = 'Paiements';
$string['settings_fakescript'] = 'Comportement du faux prestataire';
$string['settings_fakescript_desc'] = "Ce que le faux prestataire répond. Sans effet tant que \$CFG->pshub_fake_payments n'est pas défini dans config.php.";
$string['fakescript_pay'] = 'Payer';
$string['fakescript_decline'] = 'Refuser';
$string['fakescript_silent'] = 'Ne jamais répondre';
$string['fakescript_wrongamount'] = 'Payer un montant erroné';
$string['fakescript_wrongcurrency'] = 'Payer dans une autre devise';
$string['cta_access_pending_enrolment'] = "Votre accès à ce cours est actif, mais votre inscription n'est pas encore enregistrée. Contactez-nous si cela dure.";

// -- The learner's diary (PSH-S08-07) -----------------------------------------
$string['agenda_title'] = 'Mon agenda';
$string['agenda_lead'] = "Vos prochains cours en direct, du plus proche au plus loin.";
$string['agenda_today'] = "Aujourd'hui";
$string['agenda_tomorrow'] = 'Demain';
$string['agenda_yesterday'] = 'Hier';
$string['agenda_join'] = 'Rejoindre';
$string['agenda_details'] = 'Voir';
$string['agenda_empty'] = "Aucune séance programmée pour l'instant. Dès que vous rejoignez un cours, ses dates apparaîtront ici.";
$string['agenda_noroom'] = "La salle n'est pas encore prête.";
$string['agenda_recent_title'] = 'Ces derniers jours';
$string['agenda_export_summary'] = 'Ajouter cet agenda à mon téléphone';
$string['agenda_export_help'] = "Copiez ce lien dans l'application d'agenda de votre téléphone (Google Agenda, Calendrier iPhone) : vos séances s'y afficheront et se mettront à jour toutes seules.";
$string['agenda_export_warning'] = "⚠ Ce lien est personnel : quiconque l'obtient peut lire votre agenda. Ne le partagez pas.";
$string['agenda_export_link'] = 'Ouvrir le lien de mon agenda';

// -- Notification preferences (PSH-S08-01) ------------------------------------
$string['notif_title'] = 'Mes notifications';
$string['notif_lead'] = "Ce que nous vous disons, et où ça vous arrive.";
$string['notif_saved'] = 'C’est enregistré.';
$string['notif_save'] = 'Enregistrer';
$string['notif_recommended'] = 'recommandé';
$string['notif_all_legend'] = 'Tout couper';
$string['notif_all_off'] = 'Ne rien m’envoyer du tout';
$string['notif_all_help'] = "Coupé, plus aucun message ne part — ni dans l’application, ni par courriel. Y compris les rappels avant vos séances.";
$string['notif_all_on_notice'] = "Vous avez tout coupé : les réglages ci-dessous sont conservés mais ne s’appliquent pas.";
$string['notif_none'] = "Aucun canal n’est disponible sur ce site pour le moment.";
$string['notif_email_goes_to'] = 'Les courriels partent à {$a}.';
$string['notif_scope'] = "Ces réglages concernent les messages de PS Language Hub. Les notifications des activités Moodle (forums, devoirs) se règlent dans Moodle.";
$string['notif_channel_popup'] = "Dans l’application";
$string['notif_channel_email'] = 'Par courriel';
$string['notif_channel_airnotifier'] = 'Sur mon téléphone';
$string['notif_channel_sms'] = 'Par SMS';
$string['notif_sessionreminder'] = 'Avant chaque séance en direct';
$string['notif_sessionreminder_desc'] = "Un rappel la veille, un autre une heure avant, un dernier dix minutes avant. C’est ce qui vous évite de rater un cours.";
$string['notif_coursereviewed'] = 'Décision sur un cours que vous avez soumis';
$string['notif_coursereviewed_desc'] = "Quand votre cours est publié, ou refusé avec la raison.";
$string['notif_coursesubmitted'] = 'Un cours attend votre validation';
$string['notif_coursesubmitted_desc'] = "Quand une enseignante soumet un cours à la validation.";

// -- Our own application: device registration (PSH-S08-06) --------------------
$string['device_badtoken'] = "Ce jeton d’appareil est vide ou trop long.";
$string['device_badplatform'] = "Plateforme inconnue. Attendu : android, ios ou web.";

// -- Site health: can this site send mail at all? -----------------------------
$string['check_mailer_name'] = 'Envoi des courriels';
$string['check_mailer_ok'] = 'Le serveur de courriel répond ({$a})';
$string['check_mailer_ok_detail'] = 'Une connexion à {$a} a abouti. Cela ne garantit pas que le message sera accepté, seulement que quelqu’un écoute.';
$string['check_mailer_down'] = 'Le serveur de courriel ne répond pas ({$a})';
$string['check_mailer_down_detail'] = 'Aucune confirmation d’inscription, aucune décision de validation et aucun rappel de séance ne partira. Les actions, elles, continuent d’aboutir : un cours approuvé l’est vraiment, même si la notification échoue. Détail : {$a}';
$string['check_mailer_nosmtp'] = 'Aucun serveur SMTP configuré';
$string['check_mailer_nosmtp_detail'] = 'Le site utilise la fonction mail() de PHP, qui ne peut pas être vérifiée d’ici. Réglez « smtphosts » pour que cette vérification ait un sens.';
