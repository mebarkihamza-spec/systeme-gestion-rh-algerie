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

    return $pdo;
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
