# PS Language Hub — guide pas à pas

Comment ouvrir la plateforme, créer un cours, le publier, lui donner des dates, et le
suivre comme apprenant. Écrit pour être suivi ligne par ligne sur le poste de
développement, sans rien connaître de Moodle.

**Dernière vérification : 11 octobre 2026**, tout ce qui suit a été exécuté sur la machine.

---

## 1. Démarrer la plateforme

Rien ne répond si Apache et MySQL ne tournent pas — c'est le cas après chaque redémarrage
du poste.

1. Ouvrir **Laragon**.
2. Cliquer sur **Démarrer tout** (le bouton vert). Apache et MySQL s'allument ensemble.
3. Ouvrir `http://pshub-moodle.test/` — la page d'accueil publique doit s'afficher.

Si Laragon est déjà ouvert mais que le site ne répond pas, c'est qu'un des deux services
est arrêté : le bouton affiche alors **Démarrer** au lieu de **Arrêter**.

> ⚠️ Le même Apache sert aussi `perfex_crm.test`. Ne l'arrêtez pas pour « nettoyer » :
> vous couperiez l'autre site en même temps.

### Le troisième service : la boîte aux lettres

La plateforme envoie de vrais courriels — confirmation d'inscription, cours publié,
rappels de séance. Sur ce poste ils sont tous capturés par **Mailpit** : rien ne sort
vers une vraie adresse, et tout est lisible. Laragon ne le démarre pas tout seul.

```
C:\laragon\bin\mailpit\1.22.3\mailpit.exe --smtp 127.0.0.1:2525 --listen 127.0.0.1:8026 --smtp-auth-accept-any --smtp-auth-allow-insecure
```

Plus simple : double-cliquez **`tools\start-mailpit.cmd`** dans le projet. Il vérifie d'abord si Mailpit tourne, donc vous pouvez le lancer deux fois sans rien casser.

Puis ouvrez **<http://127.0.0.1:8026/>** : c'est la boîte de réception de tout le monde.

> **S'il n'est pas lancé**, vous verrez tôt ou tard une page d'erreur rouge disant
> « Error calling message processor email » après avoir approuvé un cours ou rejoint
> une séance. **Votre action a réussi quand même** — le cours est bien publié,
> l'inscription bien enregistrée. Seul le courriel s'est perdu. Relancez Mailpit et
> continuez.
>
> Pour savoir si c'est le cas sans attendre l'erreur : *Administration du site → Serveur
> → État du système*, ligne **Envoi des courriels**.

---

## 2. Les comptes

Tous les mots de passe sont ceux du poste de développement. Ils n'ont pas vocation à
sortir d'ici.

| Identifiant | Mot de passe | Qui c'est | Ce qu'il peut faire |
|---|---|---|---|
| `admin` | `PsHub#Dev2026` | Administrateur du site | Tout : approuver les cours, la mise en avant, les réglages |
| `adele` | `PsHub#Teacher2026` | Adèle Ngo Bell, enseignante | Créer des cours, les soumettre, planifier les séances |
| `amina` | `PsHub#Learner2026` | Amina Mbarga, apprenante | Inscrite aux cours 2 et 3 |
| `larissa` | `PsHub#Test2026` | Larissa Etoga, apprenante | Inscrite au cours 9, celui à douze séances |
| `brigitte` | `PsHub#Test2026` | Brigitte Nkoulou, apprenante | Inscrite à **aucun** cours. Créée le 10 octobre 2026 **par le formulaire d'inscription**, confirmation par courriel comprise |

Connexion : `http://pshub-moodle.test/login/index.php`

**Où chacun atterrit après la connexion**

- Une apprenante arrive dans **son espace** (`/local/pshub/learn/index.php`) — jamais sur
  une page Moodle.
- L'enseignante et l'admin arrivent sur le tableau de bord Moodle ; leurs consoles sont
  dans le menu du haut : **Espace enseignant** et **Administration PS Hub**.

---

## 3. Créer un cours (enseignante)

Connectez-vous en **adele**, puis ouvrez **Espace enseignant** dans le menu du haut.

### 3.1 Le formulaire

Cliquez sur **Créer un cours dans Moodle** → `/local/pshub/teacher/course.php`

Le formulaire demande seulement ce dont le catalogue a besoin :

| Champ | À savoir |
|---|---|
| **Intitulé** | Ce que verront les visiteurs. L'identifiant technique est fabriqué tout seul — on ne vous demandera jamais d'en inventer un |
| **Description** | Le texte qu'on lit **avant de payer**. Dites ce qu'on y apprend et à qui il s'adresse |
| **Langue enseignée** | Liste déroulante. Pour en ajouter une : *Administration du site → Plugins → PS Language Hub → Langues enseignées* |
| **Niveau CECR** | A1 à C2 |
| **Public** | Adultes ou enfants |
| **Durée d'une séance** | 50 minutes au maximum. Vous pouvez prévoir plus court ; plus long sera ramené à la limite |
| **Cours en tête-à-tête** | Cochez-la et la taille de groupe disparaît |
| **Apprenants par groupe** | Deux au minimum, sinon c'est un tête-à-tête |
| **Accès** | Gratuit ou payant |
| **Tarif (FCFA)** | Obligatoire et supérieur à zéro si le cours est payant |

Enregistrez. Le cours est créé **en brouillon** et **invisible** : personne ne le voit
encore.

### 3.2 Ajouter le contenu

Le contenu — documents, quiz, devoirs — s'écrit dans Moodle, comme d'habitude. Le lien
**Ouvrir** sur la ligne du cours y mène. Le portail ne reproduit pas l'éditeur de Moodle,
et l'encadré en bas de la barre latérale le dit.

### 3.3 Soumettre à validation

Sur la console, le bouton **Soumettre à validation** apparaît sur les cours en brouillon
ou rejetés.

Si quelque chose manque, la page vous le dit **avant** de cliquer : description absente,
langue ou niveau non renseignés, cours payant à zéro franc.

---

## 4. Approuver le cours (administrateur)

Connectez-vous en **admin**, menu du haut → **Administration PS Hub**.

Le cours attend dans **Cours en attente de validation**, le plus ancien en premier.

- **Approuver** → une page de confirmation, puis le cours est publié et devient visible au
  catalogue. L'enseignante reçoit une notification — lisible dans la boîte aux lettres,
  « Votre cours est publié ».
- **Rejeter** → on vous demande **pourquoi**, et c'est obligatoire. Ce texte part à
  l'enseignante : c'est sa seule indication de ce qu'il faut corriger. Elle pourra alors
  modifier son cours et le resoumettre.

Chaque décision est journalisée. Rien ne se publie en suivant un simple lien.

> **Au 10 octobre 2026, aucun cours n'attend de validation** sur ce poste : ils ont tous
> été traités. Pour refaire la démonstration, créez un cours en **adele** et soumettez-le
> (section 3) ; il apparaîtra aussitôt dans la file de l'administrateur.

---

## 5. Donner des dates au cours

Un cours sans séance est un cours que personne ne peut suivre. Toujours en **adele**, sur
la ligne du cours, cliquez sur **Séances**.

→ `/local/pshub/teacher/sessions.php?courseid=NUMÉRO`

Pour chaque séance :

1. **Intitulé** — « Séance 3 — au restaurant » vaut mieux que « Séance 3 » : c'est ce que
   les apprenants verront dans leur agenda.
2. **Début** — date et heure.
3. **Durée** — 50 minutes maximum.

Répétez pour chaque date. Le cours de démonstration « Anglais A2 — conversation pratique »
en compte douze, une par semaine.

Ensuite, chaque séance peut être **déplacée** ou **annulée**. Déplacer une séance déplace
aussi l'entrée d'agenda et remet les rappels en file ; annuler la retire de l'agenda, et
plus personne n'est prévenu.

### Ce qui part tout seul

- Une **entrée d'agenda** par séance, visible de l'enseignante et de tous les inscrits.
  C'est elle que l'apprenante retrouve dans **Mon agenda** (section 7) et dans son
  téléphone si elle s'y abonne.
- Des **rappels** à 24 h, 1 h et 10 min avant chaque séance, à l'enseignante et aux
  apprenants, dans la langue de chacun.

---

## 6. S'inscrire et suivre le cours (apprenante)

Connectez-vous en **larissa**, ou créez un compte depuis la page d'accueil.

### Créer un compte, du début à la fin

C'est le seul parcours du produit qui passe par un courriel, donc le seul où il faut
ouvrir la boîte aux lettres.

1. Page d'accueil → **Créer un compte**.
2. Le formulaire demande en plus un **numéro Mobile Money** : c'est celui qui servira à
   payer, et il est enregistré dès l'inscription.
3. Après validation, la page dit « un message vous a été envoyé ». **Le compte n'est pas
   encore utilisable** à ce stade.
4. Ouvrir **<http://127.0.0.1:8026/>**, ouvrir le message « PS Language Hub : confirmation
   de l'ouverture du compte », cliquer son lien.
5. Le lien confirme le compte **et connecte la personne** : elle arrive directement dans
   son espace.

Vérifié de bout en bout le 10 octobre 2026 avec le compte `brigitte`.

### Ensuite

1. **Explorer les cours** → le catalogue, filtrable par langue, niveau, type et accès.
2. Ouvrir un cours → sa page publique, avec le tarif et **les prochaines dates**.
3. **Rejoindre ce cours** → l'inscription est immédiate si le cours est gratuit.
4. **Ma progression** → les cours suivis, les séances à venir, les XP.

Sur la page d'un cours suivi : les séances, la progression (séances suivies sur séances
programmées) et le contenu du cours.

---

## 7. Mon agenda

Dans un produit où tous les cours sont en direct, c'est la page qu'une apprenante ouvre le
plus souvent : non pas pour lire quelque chose, mais pour savoir **quand elle est attendue
et si elle peut entrer**.

Menu de gauche → **Mon agenda** → `/local/pshub/learn/calendar.php`

C'est une liste de journées, pas un calendrier mensuel. Sur un téléphone, une grille donne
à chaque jour une case trop petite pour les deux seules choses qui comptent : l'heure, et
si la porte est ouverte.

| Ce qu'on voit | Ce que ça veut dire |
|---|---|
| **Aujourd'hui**, **Demain** | Pas une date à déchiffrer. Au-delà, le jour est écrit en toutes lettres |
| **Rejoindre** | La porte est ouverte **et** la salle existe |
| **Voir** | Tout le reste : trop tôt, pas encore de salle, accès échu. La page de la séance dit laquelle |
| **Ces derniers jours** | Les séances passées de la semaine, pour voir ce qu'on a manqué |

Le bouton n'est jamais deviné à partir de l'heure : il vient de la même vérification que la
porte elle-même. L'agenda ne peut donc pas proposer une entrée que le salon refusera.

### Mettre l'agenda dans son téléphone

En bas de la page : **Ajouter cet agenda à mon téléphone**. Le lien se colle dans Google
Agenda ou le Calendrier de l'iPhone, et les séances s'y mettent à jour toutes seules.

> ⚠️ **Ce lien est un mot de passe déguisé.** Qui l'obtient peut lire l'agenda de la
> personne. La page le dit à l'écran ; ne le collez pas dans un groupe WhatsApp.

---

## 8. Rejoindre une séance en direct

Cliquez sur une séance : vous arrivez dans **le salon**.

Le salon répond toujours, et toujours avec une raison :

| Ce qu'il dit | Pourquoi |
|---|---|
| **Rejoindre la séance** | La porte est ouverte et la salle existe |
| La salle ouvre à telle heure | Elle ouvre **15 minutes** avant le début |
| La salle n'est pas encore prête | L'heure est bonne mais aucune salle n'a été créée |
| Le groupe est complet | La taille de groupe du cours est atteinte |
| Cette séance est terminée / annulée | — |
| Votre accès ne couvre plus cette séance | Inscription ou paiement échu |

L'heure affichée en bas de la page (« Il est 17:46 sur la plateforme ») est celle du
**serveur**. Si votre téléphone est déréglé, c'est ainsi que vous le verrez.

> **Le fuseau de la plateforme est Africa/Douala** (UTC+1, sans changement d'heure),
> corrigé le 11 octobre 2026 : le site était sur Europe/London, qui avance et recule ses
> horloges deux fois par an. Chaque personne peut choisir le sien dans son profil — une
> apprenante à Paris voit ses séances à l'heure de Paris.

**Une séance est joignable tout de suite sur ce poste** : « Anglais A2 — conversation
pratique », Séance 1. Elle est reliée à une vraie salle BigBlueButton.

---

## 9. Régler ses notifications

En bas du menu de gauche : **Mes notifications** → `/local/pshub/notifications.php`

La page pose des questions, pas une grille. Chaque ligne décrit **ce qui arrive**, et les
cases disent **où ça doit vous parvenir** : dans l'application, par courriel.

Elle ne montre que ce que vous pouvez recevoir :

| Qui | Ce qu'on lui demande |
|---|---|
| Une apprenante | Une seule question : les rappels avant ses séances |
| Une enseignante | Les rappels, et les décisions sur les cours qu'elle a soumis |
| Un administrateur | Les deux, plus les cours qui attendent sa validation |

**« Ne rien m'envoyer du tout »** en haut coupe absolument tout, y compris les rappels de
séance. Ce n'est pas un réglage de courriel : plus rien ne part, nulle part. Quand c'est
coché, le reste de la page s'affiche en grisé et dit qu'il ne s'applique pas.

> Un canal n'apparaît que s'il peut vraiment livrer. La notification mobile est donc
> absente tant que l'application n'est pas installée — une case à cocher qui ne livre rien
> est pire que pas de case du tout.

Ces réglages sont ceux de Moodle, écrits autrement. Les modifier ici ou là-bas revient au
même ; il n'y a pas deux endroits où la réponse est stockée.

---

## 10. Ce qui ne marche pas encore, et pourquoi

Rien de ce qui suit n'est un bug : ce sont des choses qui attendent une décision ou un
compte.

| Ce qui manque | Ce qui le bloque |
|---|---|
| **Acheter un cours payant** | Il n'y a pas encore de page de paiement. Un cours payant affiche son prix et s'arrête là. En attendant, un administrateur peut accorder l'accès à la main |
| **Un serveur BigBlueButton à nous** | Le poste utilise le **serveur de test public** de BigBlueButton. Il fonctionne, mais : les réunions y sont publiques, il **limite toute séance à 10 minutes** quoi qu'on lui demande, et il peut être indisponible sans prévenir. À ne jamais utiliser pour un vrai cours |
| **Les rappels automatiques** | Ils partent par le cron de Moodle. Sur ce poste, le cron ne tourne pas tout seul : lancez-le à la main (voir ci-dessous) |

### Lancer le cron à la main

```
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe C:\laragon\www\pshub-moodle\admin\cli\cron.php
```

Deux tâches nous appartiennent et tournent chaque minute :

- **Envoyer les rappels de séance**
- **Clôturer les séances qui dépassent leur fin** — sans elle, une séance terminée
  continue de se dire « en direct ».

---

## 11. Les cours déjà en place

Relevé en base le 10 octobre 2026, et non de mémoire.

| № | Cours | État | Accès | Séances | Salle |
|---|---|---|---|---|---|
| 2 | Anglais professionnel B1 | publié | payant 15 000 F | 5 | — |
| 3 | Espagnol débutant, premiers échanges | publié | gratuit | 2 | — |
| 4 | Préparation IELTS, module oral | publié | payant 28 500 F | 0 | — |
| 5 | Français des affaires B2 | publié | payant 18 000 F | 0 | — |
| 6 | Allemand A2 | publié | payant 12 000 F | 1 | — |
| 8 | Allemand B1 — conversation professionnelle | publié | payant 22 000 F | 0 | — |
| 9 | **Anglais A2 — conversation pratique** | publié | gratuit | **12** | **1** |
| 11 | Italien A1 — premiers pas | publié | gratuit | 0 | — |

Le cours 9 est celui à montrer : douze séances, une salle BigBlueButton reliée, une
apprenante inscrite.

Le cours 11 a servi à vérifier le parcours complet — création, soumission, approbation,
notification — après la correction du courriel. Il est vide, et c'est normal.

> Le cours **10**, intitulé « test », était un reste de banc d'essai : il est repassé en
> brouillon et masqué le 10 octobre 2026, donc hors catalogue. Rien ne dépend de lui, il
> peut être supprimé.
