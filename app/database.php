<?php

function getDbConnection()
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $dbFile = $dir . '/rh.db';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function initDatabase()
{
    $pdo = getDbConnection();

    // Utilisateurs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'admin',
            station TEXT DEFAULT 'Bahia'
        )
    ");

    // Stations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            location TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Employés
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            station_id INTEGER NOT NULL,
            matricule TEXT NOT NULL UNIQUE,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            cin TEXT,
            department TEXT,
            position TEXT,
            email TEXT,
            phone TEXT,
            hire_date TEXT,
            status TEXT DEFAULT 'Actif',
            base_salary REAL DEFAULT 0,
            FOREIGN KEY(station_id) REFERENCES stations(id)
        )
    ");

    // Modèles de contrats
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contract_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            position TEXT NOT NULL,
            template_text TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Contrats
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contracts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            template_id INTEGER,
            type TEXT NOT NULL,
            start_date TEXT NOT NULL,
            end_date TEXT,
            base_salary REAL DEFAULT 0,
            generated_document TEXT,
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(employee_id) REFERENCES employees(id),
            FOREIGN KEY(template_id) REFERENCES contract_templates(id)
        )
    ");

    // Paies
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payrolls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            month TEXT NOT NULL,
            base_salary REAL DEFAULT 0,
            total_bonus REAL DEFAULT 0,
            total_allowances REAL DEFAULT 0,
            total_deductions REAL DEFAULT 0,
            axa_insurance REAL DEFAULT 1800,
            net_salary REAL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY(employee_id) REFERENCES employees(id)
        )
    ");

    // Présence
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            arrival_time TEXT,
            departure_time TEXT,
            present INTEGER DEFAULT 1,
            delay_minutes INTEGER DEFAULT 0,
            absence_reason TEXT,
            FOREIGN KEY(employee_id) REFERENCES employees(id)
        )
    ");

    // Alertes de contrats
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contract_alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contract_id INTEGER NOT NULL,
            employee_id INTEGER NOT NULL,
            alert_date TEXT NOT NULL,
            alerted INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(contract_id) REFERENCES contracts(id),
            FOREIGN KEY(employee_id) REFERENCES employees(id)
        )
    ");

    // Vérifier si les stations existent
    $stationCount = (int) $pdo->query('SELECT COUNT(*) FROM stations')->fetchColumn();
    if ($stationCount === 0) {
        $pdo->prepare('INSERT INTO stations (name, location) VALUES (?, ?)')->execute(['Bahia', 'Alger']);
        $pdo->prepare('INSERT INTO stations (name, location) VALUES (?, ?)')->execute(['Belgayed', 'Béjaïa']);
    }

    // Vérifier si admin existe
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $pdo->prepare('INSERT INTO users (username, password, role, station) VALUES (?, ?, ?, ?)')->execute([
            'admin',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            'Bahia'
        ]);
        $pdo->prepare('INSERT INTO users (username, password, role, station) VALUES (?, ?, ?, ?)')->execute([
            'belgayed',
            password_hash('belgayed123', PASSWORD_DEFAULT),
            'admin',
            'Belgayed'
        ]);
    }

    // Vérifier si employés existent
    $employeeCount = (int) $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
    if ($employeeCount === 0) {
        $bahiaStationId = (int) $pdo->query("SELECT id FROM stations WHERE name = 'Bahia'")->fetchColumn();
        $belgayedStationId = (int) $pdo->query("SELECT id FROM stations WHERE name = 'Belgayed'")->fetchColumn();

        $pdo->prepare("
            INSERT INTO employees
            (station_id, matricule, first_name, last_name, cin, department, position, email, phone, hire_date, status, base_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $bahiaStationId, 'EMP-001', 'Lina', 'Bensaid', '123456789', 'RH', 'Responsable RH', 'lina@bahia.dz', '0550000001', '2024-01-15', 'Actif', 85000
        ]);

        $pdo->prepare("
            INSERT INTO employees
            (station_id, matricule, first_name, last_name, cin, department, position, email, phone, hire_date, status, base_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $belgayedStationId, 'EMP-002', 'Yacine', 'Cherif', '987654321', 'Finance', 'Comptable', 'yacine@belgayed.dz', '0550000002', '2023-06-10', 'Actif', 76000
        ]);
    }

    // Vérifier si modèles de contrats existent
    $templateCount = (int) $pdo->query('SELECT COUNT(*) FROM contract_templates')->fetchColumn();
    if ($templateCount === 0) {
        $responsableRHTemplate = "CONTRAT DE TRAVAIL

Entre:
L'entreprise « Groupe Bahia-Belgayed »
Représentée par : [REPRESENTANT]

Et:
Monsieur / Madame : [EMPLOYEE_NAME]
Demeurant à : [ADDRESS]
CIN N° : [CIN]

Il a été convenu et arrêté ce qui suit :

ARTICLE 1 : OBJET DU CONTRAT
Le présent contrat a pour objet de régir les relations de travail entre l'entreprise et le salarié conformément à la législation algérienne applicable.

ARTICLE 2 : POSTE ET FONCTIONS
Le salarié est engagé au poste de : [POSITION]
Le salarié exercera ses fonctions au sein du département : [DEPARTMENT]

ARTICLE 3 : TYPE DE CONTRAT
Type : [CONTRACT_TYPE]
Durée : [DURATION]

ARTICLE 4 : RÉMUNÉRATION
Salaire mensuel brut : [BASE_SALARY] DZD
Versement : à la fin de chaque mois

ARTICLE 5 : HORAIRE DE TRAVAIL
Horaire : 40 heures par semaine
Horaire quotidien : 08h00 - 17h00 avec pause de 12h00 à 13h00

ARTICLE 6 : ASSURANCES SOCIALES
Le salarié bénéficie de la couverture sociale par mutuelle Axa Assurance.
Cotisation mensuelle : 1800 DZD déductible du salaire.

ARTICLE 7 : CONGÉS
Le salarié a droit à 30 jours de congés annuels selon la législation algérienne.

ARTICLE 8 : CONDITIONS DE RUPTURE
La rupture du contrat est soumise aux dispositions du Code du travail algérien.
Préavis : 15 jours pour le CDI.

ARTICLE 9 : CONFIDENTIALITÉ
Le salarié s'engage à respecter la confidentialité des informations professionnelles.

Fait à [LOCATION], le [DATE]

Pour l'entreprise :                                Pour le salarié :
Signature                                          Signature";

        $pdo->prepare("
            INSERT INTO contract_templates (name, position, template_text)
            VALUES (?, ?, ?)
        ")->execute(['Responsable RH', 'Responsable RH', $responsableRHTemplate]);

        $comptableTemplate = "CONTRAT DE TRAVAIL

Entre:
L'entreprise « Groupe Bahia-Belgayed »
Représentée par : [REPRESENTANT]

Et:
Monsieur / Madame : [EMPLOYEE_NAME]
Demeurant à : [ADDRESS]
CIN N° : [CIN]

Il a été convenu et arrêté ce qui suit :

ARTICLE 1 : OBJET DU CONTRAT
Le présent contrat a pour objet de régir les relations de travail entre l'entreprise et le salarié conformément à la législation algérienne applicable.

ARTICLE 2 : POSTE ET FONCTIONS
Le salarié est engagé au poste de : [POSITION]
Le salarié exercera ses fonctions au sein du département : [DEPARTMENT]

ARTICLE 3 : TYPE DE CONTRAT
Type : [CONTRACT_TYPE]
Durée : [DURATION]

ARTICLE 4 : RÉMUNÉRATION
Salaire mensuel brut : [BASE_SALARY] DZD
Versement : à la fin de chaque mois

ARTICLE 5 : ASSURANCES SOCIALES
Le salarié bénéficie de la couverture sociale par mutuelle Axa Assurance.
Cotisation mensuelle : 1800 DZD déductible du salaire.

ARTICLE 6 : CONGÉS
Le salarié a droit à 30 jours de congés annuels selon la législation algérienne.

ARTICLE 7 : CONDITIONS DE RUPTURE
La rupture du contrat est soumise aux dispositions du Code du travail algérien.
Préavis : 15 jours pour le CDI.

ARTICLE 8 : CONFIDENTIALITÉ
Le salarié s'engage à respecter la confidentialité des informations professionnelles.

Fait à [LOCATION], le [DATE]

Pour l'entreprise :                                Pour le salarié :
Signature                                          Signature";

        $pdo->prepare("
            INSERT INTO contract_templates (name, position, template_text)
            VALUES (?, ?, ?)
        ")->execute(['Comptable', 'Comptable', $comptableTemplate]);
    }

    // Modèle CDD Chef d'atelier (ajouté aussi aux bases déjà initialisées)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contract_templates WHERE name = ?');
    $stmt->execute(["Chef d'atelier - CDD"]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare("
            INSERT INTO contract_templates (name, position, template_text)
            VALUES (?, ?, ?)
        ")->execute(["Chef d'atelier - CDD", "Chef d'atelier", getChefAtelierCddTemplate()]);
    }

    return $pdo;
}

function getChefAtelierCddTemplate()
{
    return "CONTRAT DE TRAVAIL À DURÉE DÉTERMINÉE

Entre:
L'entreprise « Groupe Bahia-Belgayed » - Pôle Vidange
Siège : [COMPANY_ADDRESS] - RC : [RC] - NIF : [NIF] - N° employeur CNAS : [CNAS_EMPLOYER]
Représentée par : [REPRESENTANT]

Et:
Monsieur / Madame : [EMPLOYEE_NAME]
Né(e) le : [BIRTH_DATE] à [BIRTH_PLACE]
Demeurant à : [ADDRESS]
CIN N° : [CIN] - NIN : [NIN]

Il a été convenu et arrêté ce qui suit :

ARTICLE 1 : OBJET ET CADRE LÉGAL
Le présent contrat est régi par la loi n° 90-11 du 21 avril 1990 relative aux relations de travail, l'ordonnance n° 97-03 relative à la durée légale du travail, la législation de sécurité sociale et le règlement intérieur de l'entreprise.

ARTICLE 2 : NATURE ET MOTIF DU CONTRAT
Contrat à durée déterminée à temps plein, conclu en application de l'article 12 de la loi n° 90-11.
Motif : [CDD_REASON]

ARTICLE 3 : POSTE ET MISSIONS
Le salarié est engagé au poste de : Chef d'atelier (agent de maîtrise), département : [DEPARTMENT]
Missions principales :
- organiser et planifier le travail de l'atelier ;
- encadrer et contrôler les techniciens et vidangeurs ;
- garantir la qualité des prestations (huiles et filtres conformes aux préconisations constructeur, niveaux, serrages) ;
- accueillir et conseiller la clientèle, établir les fiches d'intervention ;
- gérer le stock d'huiles, de filtres et de consommables (inventaire mensuel) ;
- faire respecter l'hygiène, la sécurité et le port des EPI ;
- assurer le stockage et la remise à un collecteur agréé des huiles et filtres usagés (loi n° 01-19) ;
- remettre un compte rendu d'activité quotidien à la Direction.

ARTICLE 4 : DURÉE ET RENOUVELLEMENT
Du [START_DATE] au [END_DATE] inclus, soit [DURATION].
Renouvelable par avenant écrit signé avant le terme. La décision de renouvellement est notifiée au moins 15 jours avant l'échéance.

ARTICLE 5 : PÉRIODE D'ESSAI
Période d'essai de [TRIAL_PERIOD], jusqu'au [TRIAL_END_DATE] inclus (articles 18 à 20 de la loi n° 90-11).
Pendant cette période, chaque partie peut rompre le contrat sans préavis ni indemnité.

ARTICLE 6 : LIEU DE TRAVAIL
Atelier Pôle Vidange, station [STATION]. Affectation temporaire possible dans une autre station du groupe.

ARTICLE 7 : HORAIRE DE TRAVAIL
40 heures par semaine, de 08h00 à 17h00 avec pause de 12h00 à 13h00, selon planning.
Heures supplémentaires sur demande écrite, majorées d'au moins 50 %.

ARTICLE 8 : RÉMUNÉRATION
Salaire mensuel de base brut : [BASE_SALARY] DZD, plus primes et indemnités du régime interne.
Versement : à la fin de chaque mois. Retenues légales : sécurité sociale et IRG.

ARTICLE 9 : ASSURANCES SOCIALES
Déclaration à la CNAS dès l'entrée en fonction (part salariale 9 %).
Couverture complémentaire Axa Assurance : cotisation mensuelle de 1800 DZD déductible du salaire.

ARTICLE 10 : CONGÉS
2,5 jours ouvrables par mois de travail effectif. Indemnité compensatrice pour les jours non pris à l'échéance.

ARTICLE 11 : OBLIGATIONS DU SALARIÉ
Respect des horaires, du règlement intérieur et des consignes ; soin du matériel, des stocks et des fonds confiés ; non-concurrence pendant le contrat ; visite médicale d'embauche et contrôles périodiques.

ARTICLE 12 : HYGIÈNE ET SÉCURITÉ
L'employeur fournit les EPI ; le salarié les porte et les fait porter par son équipe (loi n° 88-07).

ARTICLE 13 : CONFIDENTIALITÉ
Secret sur les tarifs, fournisseurs, clientèle et chiffre d'affaires, y compris après la fin du contrat.

ARTICLE 14 : DISCIPLINE
Sanctions selon le règlement intérieur et les articles 73 à 73-4 de la loi n° 90-11.

ARTICLE 15 : FIN DU CONTRAT
À son terme sauf avenant ; pendant l'essai sans préavis ; par accord écrit ; pour faute grave ; par démission avec préavis de 15 jours ; autres cas de l'article 66 de la loi n° 90-11.
Remise d'un certificat de travail et du solde de tout compte.

ARTICLE 16 : LITIGES
Règlement amiable, puis conciliation à l'inspection du travail, puis tribunal social compétent (loi n° 90-04).

Fait à [LOCATION], le [DATE], en deux exemplaires.

Pour l'entreprise :                                Pour le salarié :
Signature et cachet                                « Lu et approuvé », signature";
}

/**
 * Crée les alertes d'un contrat : fin de période d'essai et échéance du CDD.
 * L'alerte d'échéance tombe 30 jours avant la fin pour laisser le temps
 * de décider du renouvellement (notification au moins 15 jours avant).
 */
function createContractAlerts($pdo, $contractId, $employeeId, $endDate, $trialEndDate = null)
{
    $stmt = $pdo->prepare('INSERT INTO contract_alerts (contract_id, employee_id, alert_date) VALUES (?, ?, ?)');

    if ($trialEndDate) {
        $trialAlert = (new DateTime($trialEndDate))->modify('-15 days')->format('Y-m-d');
        $stmt->execute([$contractId, $employeeId, $trialAlert]);
    }

    if ($endDate) {
        $endAlert = (new DateTime($endDate))->modify('-30 days')->format('Y-m-d');
        $stmt->execute([$contractId, $employeeId, $endAlert]);
    }
}

function getStationIdForUser($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT station FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    if ($result) {
        $stmt = $pdo->prepare('SELECT id FROM stations WHERE name = ?');
        $stmt->execute([$result['station']]);
        return (int) $stmt->fetchColumn();
    }
    return 0;
}
