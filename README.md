# BI Portal — version démonstration

Portail de Business Intelligence : rapports de vente, performance produits,
analyse des encaissements et modèles prédictifs, réunis dans une interface
web unique.

**Cette version est autonome.** Elle ne nécessite ni MySQL, ni SQL Server,
ni Python, ni licence Power BI. Déposez les fichiers sur n'importe quel
hébergement PHP ≥ 7.4 et le portail fonctionne — pour tous les visiteurs,
pas seulement sur la machine de développement.

---

## Démarrage

### Avec XAMPP

```
C:\xampp\htdocs\bi_portal\
```

Démarrez Apache, puis ouvrez <http://localhost/bi_portal/>.
MySQL n'a pas besoin d'être lancé.

### Sans XAMPP

```bash
cd bi_portal
php -S localhost:8000
```

Puis <http://localhost:8000>.

### Comptes de démonstration

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@bi.local` | `admin123` |
| Commercial | `commercial@bi.local` | `commercial123` |
| Employé | `employe@bi.local` | `employe123` |

Chaque rôle ouvre un périmètre différent — voir la matrice des droits dans
*Administration → Utilisateurs*.

---

## ⚠ Les rapports Power BI réels ne doivent pas être déployés

C'est la raison d'être de cette version.

La version initiale intégrait trois rapports par iframe :

```html
<iframe src="https://app.powerbi.com/reportEmbed?reportId=…&autoAuth=true&ctid=…">
```

Ce montage pose trois problèmes en production :

1. **Il ne fonctionne pas pour les visiteurs.** `autoAuth=true` exige une
   session Microsoft Entra ID active dans le locataire propriétaire du
   rapport. Tout autre visiteur voit un écran de connexion Microsoft, puis
   une erreur d'autorisation. Le portail paraît cassé.

2. **Il expose des références internes.** Les identifiants de rapport et de
   locataire (`ctid`) figuraient en clair dans le code publié sur GitHub.

3. **La seule alternative qui « marcherait » est pire.** « Publier sur le
   web » rend le rapport accessible à tout internaute disposant du lien,
   sans aucune authentification. À proscrire pour des données de vente.

**Solution retenue :** les trois rapports sont reconstruits en code, à
partir de données figées. Les pages attestent du travail Power BI sans rien
exposer.

| Rapport Power BI | Page du portail |
|---|---|
| Vue générale sur vente | `bi_ventes.php` |
| Performance produits | `bi_produits.php` |
| Analyse des paiements | `bi_paiements.php` |

Un bandeau rappelle cet avertissement sur chaque page. Il est piloté par la
constante `DEMO_MODE` dans `config.php`.

---

## Ce qui a changé

### Base de données → données statiques

| Avant | Après |
|---|---|
| `new mysqli("localhost","root","","bi_portal")` | Tableaux PHP dans `data/` |
| Table `users` | `data/users.php` |
| `pyodbc` vers SQL Server (`sa` / mot de passe en clair) | Supprimé |

La connexion MySQL était la première cause d'échec au déploiement : hors de
la machine de développement, la base n'existe pas et toutes les pages
tombaient sur « Erreur connexion ».

### Sécurité de l'authentification

Trois failles corrigées :

**Injection SQL** dans `login.php`. La requête était construite par
concaténation :

```php
$sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
```

Saisir `' OR '1'='1' --` dans le champ email ouvrait la session du premier
utilisateur de la table, sans mot de passe. Il n'y a plus de SQL du tout.

**Mots de passe en MD5.** Algorithme cassé depuis 2005 : une empreinte MD5
de mot de passe courant se retrouve en clair en quelques secondes.
Remplacé par `password_hash()` / `password_verify()` (bcrypt).

**CSRF sur la gestion des comptes.** L'activation/désactivation passait par
un lien `GET` : une balise `<img src="…?toggle=1">` sur une autre page
suffisait à désactiver un compte à l'insu de l'administrateur connecté.
L'action passe désormais par un `POST` accompagné d'un jeton de session.

S'y ajoutent : session régénérée à la connexion (fixation de session),
cookie `HttpOnly` + `SameSite`, message d'erreur identique que le compte
existe ou non (énumération de comptes), et un administrateur qui ne peut
plus se désactiver lui-même.

### Exécution de Python → sorties figées

`dashboard_ml.php` lançait Python depuis PHP :

```php
$MODELS_DIR = "C:/xampp/htdocs/bi_portal/models";
shell_exec("python /tmp/xxx.py");
```

Chemin absolu Windows, `shell_exec()` désactivé chez la quasi-totalité des
hébergeurs, et aucun serveur web ne dispose de joblib, scikit-learn ou
prophet. Les sorties des modèles sont maintenant dans `data/ml_data.php`.

### Interface

Thème corporate clair remplaçant le thème sombre, menu latéral par rôle,
graphiques accompagnés de leur tableau de valeurs, mise en page adaptée au
mobile. La palette de séries est validée pour le daltonisme : chaque paire
de couleurs adjacente reste distinguable en deutéranopie, protanopie et
tritanopie — et aucune information n'est portée par la couleur seule.

---

## Provenance des données

Le portail distingue explicitement deux natures de chiffres.

### Réels

Extraits des rapports Power BI et des fichiers du dépôt.

- **Tableau produits** — les colonnes se totalisent exactement aux totaux
  d'origine (655 138,00 unités et 374 907,39 DT), et les pourcentages de
  l'anneau se recalculent à partir des montants : Camel bleu,
  90 907,94 / 374 907,39 = 24,25 %, valeur identique au rapport.
- **Tableau des modes de paiement** — même contrôle
  (1 376 531,00 DT et 473 483 transactions).
- **Segmentation K-Means** — agrégation de `models/clusters_produits.csv`,
  177 produits.
- **Règles d'association** — `models/association_rules.csv` (FP-Growth).
- **Nombre de commandes par produit** — `models/clusters_produits.csv`.

### Reconstitués

Les rapports ne donnent que la forme de ces séries, pas leurs valeurs point
par point. Elles respectent les totaux réels et sont signalées comme telles
sur les pages concernées.

- Séries mensuelle, trimestrielle et journalière du chiffre d'affaires.
- Prévision hebdomadaire à 12 semaines.
- `predire_ca()` — modèle de substitution, détaillé plus bas.

### Deux correctifs de fond

**Libellés de clusters réalignés.** Le code d'origine étiquetait le
cluster 2 « Produit Moyen » et le cluster 3 « Premium ». Les chiffres disent
l'inverse : le cluster 2 réunit 66 produits à 6,95 DT de prix moyen pour
405 unités écoulées — c'est lui le segment premium à faible rotation. Les
libellés ont été corrigés.

**Mois remis dans l'ordre chronologique.** Le rapport triait les mois par
ordre alphabétique de leur libellé (octobre, novembre, décembre, février…),
ce qui rendait la saisonnalité illisible.

---

## Le modèle de prédiction

`predire_ca()` dans `data/ml_data.php` **n'est pas** le Random Forest de
`models/rf_ca_heure.pkl`. PHP ne sait pas lire un `.pkl` : il faudrait
Python, joblib et scikit-learn côté serveur.

C'est un modèle de substitution, volontairement simple et entièrement
lisible, qui reproduit l'interface du Random Forest à partir des
statistiques réelles du catalogue :

```
CA = nb_commandes
   × panier_moyen_du_produit        (CA observé / commandes observées)
   × coefficient_saisonnier(mois)   (décembre ≈ 1,63 ; juillet ≈ 0,65)
   × coefficient_de_tendance(année) (base 2023 = 1)
```

Le détail du calcul s'affiche sous chaque résultat : une prédiction
invérifiable ne sert à rien en pilotage.

---

## Remettre les vrais modèles en service

Les `.pkl` restent dans `models/`. Pour les rebrancher, l'approche saine
est un **microservice Python séparé**, jamais `shell_exec()` depuis PHP.

**1. Exposer les modèles en HTTP** (FastAPI, sur la machine qui dispose de
Python) :

```python
from fastapi import FastAPI
import joblib, numpy as np

app = FastAPI()
rf = joblib.load("models/rf_ca_heure.pkl")

@app.post("/predict")
def predict(id_produit: int, mois: int, annee: int, nb_commandes: int):
    x = np.array([[id_produit, mois, annee, nb_commandes]])
    return {"ca": round(float(rf.predict(x)[0]), 2)}
```

**2. L'appeler depuis PHP**, en remplaçant le corps de `predire_ca()` :

```php
$reponse = @file_get_contents('http://127.0.0.1:8001/predict?' . http_build_query([
    'id_produit'   => $idProduit,
    'mois'         => $mois,
    'annee'        => $annee,
    'nb_commandes' => $nbCommandes,
]));

// Repli sur le modèle de substitution si le service est indisponible :
// une page de tableau de bord ne doit pas tomber en erreur pour autant.
if ($reponse === false) {
    return predire_ca_substitution($idProduit, $mois, $annee, $nbCommandes);
}
```

Le service ne doit **jamais** être exposé publiquement, et les identifiants
de base de données doivent vivre dans une variable d'environnement — pas
dans le code, comme c'était le cas avec `sa` / `Bedis123` dans le script
Prophet d'origine.

**Si ces identifiants sont encore actifs, changez-les.** Ils ont été publiés
sur GitHub ; les retirer du code ne les retire pas de l'historique du dépôt.

---

## Passer en production

1. **Changer les mots de passe.** Générez de nouvelles empreintes :

   ```bash
   php -r "echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);"
   ```

   Collez-les dans `data/users.php`, et retirez l'encart « comptes de
   démonstration » de `login.php`.

2. **Brancher une vraie base** si les comptes doivent être gérés en ligne.
   Remplacez `all_users()` et `toggle_user()` dans `auth.php` par des
   requêtes **préparées** — jamais de concaténation.

3. **Servir en HTTPS.** Le cookie de session passe automatiquement en
   `secure` dès que le site est en HTTPS.

4. **Laisser `DEMO_MODE` à `true`** tant que les données affichées sont
   celles de cette démonstration.

---

## Structure

```
bi_portal/
├── index.php                 Redirection selon le rôle
├── login.php  logout.php     Authentification
├── admin.php                 Espace administrateur
├── dashboard_commercial.php  Espace commercial
├── dashboard_employe.php     Espace employé
├── dashboard_ml.php          Prédiction & data mining
├── config_employes.php       Gestion des utilisateurs
├── bi_ventes.php             Rapport « Vue générale sur vente »
├── bi_produits.php           Rapport « Performance produits »
├── bi_paiements.php          Rapport « Analyse des paiements »
├── config.php                Configuration, mise en forme des nombres
├── auth.php                  Sessions, rôles, jetons CSRF
├── data/
│   ├── users.php             Annuaire statique
│   ├── bi_data.php           Données des rapports, saisonnalité
│   └── ml_data.php           Sorties des modèles
├── partials/layout.php       Gabarit commun
├── assets/
│   ├── theme.css             Charte graphique
│   ├── charts.js             Réglages communs des graphiques
│   └── vendor/chart.umd.js   Chart.js 4.4.1 (embarqué, pas de CDN)
└── models/                   Modèles et exports des notebooks
```

Chart.js est embarqué plutôt que chargé depuis un CDN : le portail
fonctionne hors ligne et ne dépend pas d'un service tiers.

---

## Dépendances

PHP ≥ 7.4. Aucune extension particulière, aucun gestionnaire de paquets,
aucune base de données.
