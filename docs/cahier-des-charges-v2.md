# Cahier des charges — PS Language Hub

**Plateforme e-learning de langues basée sur Moodle**
Devise : *Learn. Speak. Succeed.*
Version : 2.0 — Septembre 2026

> Document de référence client, transcrit fidèlement — ne pas modifier sans validation du client.
> La traduction en exigences techniques vit dans
> [`../functional-spec-ps-language-hub.md`](../functional-spec-ps-language-hub.md) (v3.0).
> La version 1.0 (juin 2026) est le PDF `cahier_des_charges-elearning.pdf` à la racine.

---

## 1. Contexte et présentation du projet

PS Language Hub est une plateforme d'apprentissage des langues en ligne dont la particularité est que **tous les cours sont dispensés en direct (live)**, en sessions interactives entre enseignants et apprenants. La plateforme s'appuie sur **Moodle** comme cœur LMS (gestion des cours, utilisateurs, évaluations, certificats, XP), complété par :

- une **plateforme web publique et spécialisée pour les apprenants** (vitrine + espace étudiant), avec une interface moderne inspirée du dashboard de référence fourni (type « Sharecourse ») ;
- une **application mobile dédiée aux apprenants** (Android en priorité, iOS ensuite), optimisée pour les **connexions à faible bande passante** ;
- l'**interface Moodle standard** réservée à l'administration et aux enseignants.

Le projet cible principalement un public en Afrique francophone (Cameroun en premier lieu), ce qui impose des contraintes fortes : faible débit internet, paiement par Mobile Money, usage majoritairement mobile.

---

## 2. Objectifs

1. Offrir des cours de langues **100 % en direct**, interactifs, avec visioconférence, partage d'écran et tableau blanc collaboratif.
2. Centraliser la gestion pédagogique, financière et administrative dans Moodle.
3. Offrir aux apprenants une expérience simple, moderne et légère (web + mobile), distincte de l'interface Moodle classique.
4. Mettre en place un système de motivation : **XP, compétences, certificats**.
5. Garantir un fonctionnement fiable même en **connexion 2G/3G faible**.

---

## 3. Architecture générale

| Composant | Technologie | Utilisateurs |
|---|---|---|
| LMS cœur | Moodle (dernière version LTS) | Gestionnaire, Admin principal, Enseignants |
| Classe virtuelle | BigBlueButton (plugin Moodle natif) — visio, partage d'écran, whiteboard multi-utilisateurs, mode audio seul | Tous |
| Plateforme web apprenants | Front-end dédié (React/Vue) connecté à Moodle via **Moodle Web Services API (REST)** | Apprenants + visiteurs |
| Application mobile apprenants | App basée sur Moodle App (Ionic) personnalisée **ou** app native connectée à l'API Moodle | Apprenants |
| Paiements | Passerelle intégrée à Moodle : MTN Mobile Money, Orange Money, carte bancaire (validation manuelle possible par l'admin) | Apprenants → Admin |
| Notifications | Moodle Messaging + push mobile (FCM) + e-mail + SMS (optionnel) | Tous |

**Principe clé :** les enseignants et administrateurs travaillent directement dans Moodle ; les apprenants n'interagissent **jamais** avec l'interface Moodle brute — uniquement via le site apprenant et l'application mobile.

---

## 4. Rôles et permissions

### 4.1 Gestionnaire de site (1 compte)
- Supervision technique globale de la plateforme (Moodle, serveurs, sauvegardes, mises à jour).
- Création et gestion des comptes administrateurs principaux.
- Accès complet à la configuration système.

### 4.2 Administrateur principal
- **Validation des cours** soumis par les enseignants avant publication.
- Création de cours et de **types/catégories de cours** avec leurs paramètres (ex. : *-20 %*, *Hot*, *Gratuit*, *Examen*).
- **Validation des paiements** et suivi comptable (rapports de revenus, historique des transactions).
- **Création des tarifs** des cours et promotions.
- Configuration pédagogique : **XP, plans de compétences, certificats**, exigences pédagogiques.
- **Gestion des enseignants** : création de comptes, assignation des compétences/exigences à couvrir, suivi de leur activité.
- Publication des cours mis en avant sur la page d'accueil publique.

### 4.3 Enseignant
- Création de cours **selon les exigences et compétences assignées par l'administrateur principal**.
- Soumission des cours à l'administrateur pour **validation avant publication**.
- Création des activités : **quiz, ateliers (workshops), devoirs**, ressources vidéo et audio.
- Animation des **sessions live** (visio, partage d'écran, whiteboard).
- **Suivi des apprenants** : progression, notes, présence aux sessions live.
- Suivi de ses propres cours créés (statut : brouillon / en validation / publié / rejeté).
- Réception des **rappels automatiques** avant chaque session live.

### 4.4 Apprenant
- Consultation du catalogue, inscription et paiement des cours.
- Participation aux **cours en direct** : visio, interaction sur le whiteboard et les documents partagés.
- Réalisation des **devoirs, quiz, ateliers** et évaluation de son cursus.
- Suivi de sa progression, de ses **XP**, de ses compétences.
- Réception de ses **certificats** en fin de parcours.
- Effectue ses **paiements** (Mobile Money / carte), consulte son historique.

---

## 5. Parcours et fonctionnalités détaillées

### 5.1 Page d'accueil publique (site apprenant)
- Affichage des **cours mis en avant**, sélectionnés et publiés par l'administrateur principal, avec leurs badges (*-20 %*, *Hot*, *Gratuit*, *Examen*).
- Consultation libre du catalogue ; **inscription ou connexion obligatoire** pour s'inscrire à un cours ou y accéder.
- **Lien discret** de connexion pour administrateurs et enseignants (redirige vers la connexion Moodle), placé en pied de page.
- **Liens de téléchargement de l'application mobile** (Google Play / App Store) visibles dès l'accueil.
- Présentation de la marque : logo, devise *Learn. Speak. Succeed.*, sections « Comment ça marche », témoignages, FAQ, contact.

### 5.2 Types de cours et étiquettes
L'administrateur principal crée les types de cours et leurs paramètres, appliqués comme badges visibles sur les cartes de cours :

- **-20 %** (ou tout autre pourcentage) : réduction tarifaire, prix barré affiché.
- **Hot** : cours populaire, mis en avant.
- **Gratuit** : accès sans paiement (inscription requise).
- **Examen** : cours de type évaluation/certification.
- Le système doit permettre d'ajouter de **nouveaux types** sans développement (paramétrable).

### 5.3 Classe virtuelle (cœur du produit)
- Sessions **1-à-1 ou en groupe** : un ou plusieurs enseignants avec un ou plusieurs apprenants.
- **Visioconférence** avec caméra et micro pour tous les participants.
- **Partage d'écran** par l'enseignant (et par un apprenant sur autorisation).
- **Tableau blanc (whiteboard) collaboratif** : les apprenants peuvent écrire, dessiner et annoter en temps réel, y compris sur les **documents partagés** (PDF, présentations).
- Chat texte, lever la main, sondages rapides, emojis de réaction.
- **Durée maximale de 50 minutes par session live** : minuterie visible, alertes à 45 et 48 minutes, clôture automatique à 50 minutes (paramétrable par l'admin).
- **Enregistrement optionnel** des sessions pour rediffusion (selon paramétrage du cours).

### 5.4 Contenus pédagogiques
- **Supports vidéo** (capsules, replays) avec plusieurs qualités d'encodage.
- **Supports audio** (prononciation, compréhension orale) — format léger prioritaire.
- **Ateliers (module Workshop Moodle)** : évaluation par les pairs.
- **Quiz** (QCM, appariement, dictée audio…), **devoirs** avec dépôt de fichiers, feedback de l'enseignant.
- Documents téléchargeables pour consultation hors ligne (mobile).

### 5.5 Faible bande passante (exigence critique)
Les cours étant toujours en direct, la plateforme doit rester utilisable en connexion faible :

- Mode **« audio seul »** automatique quand le débit chute (la vidéo se coupe, la voix et le whiteboard restent).
- Résolutions vidéo adaptatives (démarrage en basse qualité, montée si le réseau le permet).
- Whiteboard et documents transmis en **données vectorielles légères**, pas en flux vidéo.
- Application mobile légère : mise en cache, téléchargement des supports en Wi-Fi, images compressées (WebP), indicateur de qualité de connexion.
- Objectif : session live fonctionnelle (audio + whiteboard) à partir de **~64 kbit/s** ; visio complète à partir de ~300 kbit/s.
- Reconnexion automatique en cas de coupure sans perdre la session.

### 5.6 Notifications et rappels
- **Annonces de cours** : nouvelles publications, promotions, messages de l'admin — diffusées sur le site, l'app (push) et par e-mail.
- **Rappels de sessions pour les enseignants** : notification 24 h, 1 h et 10 min avant chaque session live (push + e-mail).
- Rappels équivalents pour les apprenants inscrits.
- Calendrier intégré (sessions à venir, devoirs à rendre) synchronisable.

### 5.7 Gamification, compétences et certificats
- **XP** attribués pour : présence aux sessions live, quiz réussis, devoirs rendus, ateliers complétés (barème configuré par l'admin).
- **Plans de compétences** Moodle définis par l'admin, rattachés aux cours et aux activités.
- **Certificats** générés automatiquement à la complétion d'un cursus (modèle aux couleurs de la marque, QR code de vérification), validés selon les règles définies par l'admin.
- Tableau de bord apprenant : XP, badges, compétences acquises, certificats obtenus.

### 5.8 Paiements et comptabilité
- Paiement en ligne : **MTN Mobile Money, Orange Money**, carte bancaire ; devise principale **FCFA (XAF)**.
- Circuit : l'apprenant paie → le paiement apparaît dans le back-office → **validation par l'administrateur principal** (automatique pour les passerelles fiables, manuelle pour les virements/dépôts).
- L'accès au cours est débloqué après validation du paiement.
- Module de **comptabilité** : revenus par cours, par période, par enseignant ; export CSV/Excel ; historique complet des transactions ; factures/reçus automatiques.

---

## 6. Interface et charte graphique

### 6.1 Référence UI
Le dashboard apprenant s'inspire de la maquette fournie (type Sharecourse) : barre latérale sombre (navigation + labels), vue « Course Overview » avec cartes statistiques (cours suivis, ateliers, score moyen aux quiz, certificats), grilles de cours avec barres de progression, liste des webinaires/sessions à venir avec bouton **« Rejoindre maintenant »** et compte à rebours.

### 6.2 Charte graphique (issue des logos fournis)
- **Navy (fond/texte principal)** : `#0A1A40`
- **Dégradé signature** (logo « S ») : rose `#E8336F` → orange `#F7941D` → jaune `#FBC02D`
- **Bleus (globe/accents)** : `#0B3FA0`, `#1F6FE0`, `#2E8BFF`, `#5BA3FF`
- **Blanc** `#FFFFFF` et bleu très clair `#CFE2FF` pour les fonds
- Logos disponibles : logo-mark (icône seule), logos horizontaux clair/navy, icônes app (standard + maskable), favicons — à utiliser sur le site, l'app, les certificats et les e-mails.
- La devise tricolore **LEARN. SPEAK. SUCCEED.** (bleu / rose / orange) accompagne le logo horizontal.

### 6.3 Langues de l'interface
- Français (par défaut) et anglais, extensible.

---

## 7. Exigences non fonctionnelles

- **Sécurité** : HTTPS partout, mots de passe robustes, protection des données personnelles, rôles Moodle strictement cloisonnés, journaux d'audit (validations de cours et de paiements).
- **Performance** : pages apprenant < 3 s en 3G ; app mobile < 25 Mo.
- **Disponibilité** : 99 % ; sauvegardes quotidiennes automatiques.
- **Scalabilité** : dimensionnement BigBlueButton pour X sessions simultanées (à préciser selon le lancement) ; possibilité d'ajouter des serveurs BBB.
- **Compatibilité** : Chrome/Firefox/Safari récents ; Android 8+ ; iOS 14+.

---

## 8. Phasage proposé

**Phase 1 — Fondations (MVP)**
Installation Moodle + BigBlueButton ; rôles et workflows de validation (cours, paiements) ; types de cours et badges ; paiement Mobile Money ; site public apprenant (accueil, catalogue, inscription, connexion, lien discret admin/prof, liens app mobile) ; sessions live 50 min avec whiteboard et partage d'écran ; notifications et rappels.

**Phase 2 — Expérience apprenant**
Application mobile ; mode faible bande passante avancé (audio seul, cache hors ligne) ; XP, compétences, certificats ; tableau de bord apprenant complet.

**Phase 3 — Optimisation**
Comptabilité avancée et exports ; enregistrements/replays ; sondages en live ; améliorations basées sur les retours des premiers utilisateurs.

---

## 9. Critères d'acceptation (extraits)

1. Un enseignant crée un cours → il n'est **pas visible** publiquement tant que l'admin ne l'a pas validé.
2. Un visiteur voit les cours en avant sur l'accueil mais doit **s'inscrire/se connecter** pour y accéder.
3. Un apprenant paie par MTN MoMo → l'admin voit et valide le paiement → l'accès au cours s'ouvre.
4. Une session live se termine **automatiquement à 50 minutes**.
5. En connexion dégradée, la session bascule en **audio + whiteboard** sans déconnexion.
6. Un enseignant reçoit un **rappel** 1 h avant sa session.
7. Un apprenant qui complète son cursus reçoit son **certificat** et voit ses **XP** mis à jour.
8. Les badges *-20 %*, *Hot*, *Gratuit*, *Examen* s'affichent sur les cartes de cours et sont **paramétrables sans code**.
