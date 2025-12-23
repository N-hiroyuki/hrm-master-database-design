<?php
// --- DB接続設定 ---
$db_host = 'localhost'; $db_name = 'master_db'; $db_user = 'root'; $db_pass = '';
// --------------------

try {
    // --- データベースと接続 ---
    $pdo = new PDO("mysql:host=$db_host;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
    $pdo->exec("USE `$db_name`");
    echo "DB接続完了<br>";

    // --- テーブル削除（再実行時に備える）---
    $pdo->exec("DROP TABLE IF EXISTS `users`;");
    $pdo->exec("DROP TABLE IF EXISTS `roles`;");
    $pdo->exec("DROP TABLE IF EXISTS `fixed_roles`;");
    $pdo->exec("DROP TABLE IF EXISTS `list_options`;");
    $pdo->exec("DROP TABLE IF EXISTS `employee_custom_data`;");
    $pdo->exec("DROP TABLE IF EXISTS `employee_field_settings`;");
    $pdo->exec("DROP TABLE IF EXISTS `employees`;");
    $pdo->exec("DROP TABLE IF EXISTS `departments`;");
    $pdo->exec("DROP TABLE IF EXISTS `settings`;");
    $pdo->exec("DROP TABLE IF EXISTS `audit_logs`;");
    echo "既存テーブルを削除しました。<br>";

    // --- テーブル作成 ---

    // 1. departments テーブル
    $sql_departments = "CREATE TABLE `departments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL UNIQUE, `manager_id` INT DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_departments); echo "テーブル 'departments' を作成しました。<br>";
    
    // 2. employees テーブル (★ id から AUTO_INCREMENT を削除)
    $sql_employees = "
    CREATE TABLE `employees` (
      `id` INT PRIMARY KEY, `name` VARCHAR(255) NOT NULL,
      `name_kana` VARCHAR(255) DEFAULT NULL, `gender` VARCHAR(10) DEFAULT NULL, `birth_date` DATE DEFAULT NULL, `join_date` DATE DEFAULT NULL, `leave_date` DATE DEFAULT NULL,
      `status` VARCHAR(50) NOT NULL DEFAULT '在籍', `notes` TEXT DEFAULT NULL, `department_id` INT DEFAULT NULL, `position` VARCHAR(100) DEFAULT NULL,
      `job_title` VARCHAR(100) DEFAULT NULL, `team` VARCHAR(100) DEFAULT NULL, `supervisor_id` INT DEFAULT NULL,
      `phone` VARCHAR(30) DEFAULT NULL, `employment_type` VARCHAR(50) DEFAULT '正社員',
      `work_schedule_type` VARCHAR(50) DEFAULT 'フルタイム', `hourly_rate` DECIMAL(10, 2) DEFAULT NULL,
      `salary_grade` VARCHAR(50) DEFAULT NULL, `overtime_rule_id` INT DEFAULT NULL, `leave_type` VARCHAR(50) DEFAULT NULL,
      `leave_start_date` DATE DEFAULT NULL, `leave_end_date` DATE DEFAULT NULL, `leave_approval` TINYINT(1) NOT NULL DEFAULT 0,
      `custom_field_1` VARCHAR(255) DEFAULT NULL, `custom_field_2` VARCHAR(255) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_employees); echo "テーブル 'employees' を作成しました。<br>";
    
    // 3. roles (権限グループ) テーブル
    $sql_roles = "CREATE TABLE `roles` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL UNIQUE, `description` TEXT, `can_view_employees` TINYINT(1) NOT NULL DEFAULT 1, `can_edit_employees` TINYINT(1) NOT NULL DEFAULT 0, `can_manage_departments` TINYINT(1) NOT NULL DEFAULT 0, `can_manage_fixed_roles` TINYINT(1) NOT NULL DEFAULT 0, `can_manage_roles` TINYINT(1) NOT NULL DEFAULT 0, `can_manage_settings` TINYINT(1) NOT NULL DEFAULT 0, `can_view_audit_logs` TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_roles); echo "テーブル 'roles' を作成しました。<br>";

    // 4. users (ログインアカウント) テーブル (★ usernameにUNIQUEを再追加)
    $sql_users = "
    CREATE TABLE `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL UNIQUE,
      `username` VARCHAR(100) NOT NULL UNIQUE, `password` VARCHAR(255) NOT NULL,
      `role_id` INT DEFAULT NULL, `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_users); echo "テーブル 'users' を作成しました。<br>";

    // (fixed_roles, settings, employee_field_settings, etc... のCREATE文は変更なし)
    $sql_fixed_roles = "CREATE TABLE `fixed_roles` (`id` INT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `employee_id` INT DEFAULT NULL UNIQUE, FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_fixed_roles); echo "テーブル 'fixed_roles' を作成しました。<br>";
    $sql_settings = "CREATE TABLE `settings` (`id` INT AUTO_INCREMENT PRIMARY KEY, `label` VARCHAR(255) NOT NULL UNIQUE, `value` TEXT, `is_active` TINYINT(1) NOT NULL DEFAULT 0, `is_custom` TINYINT(1) NOT NULL DEFAULT 0, `is_protected` TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_settings); echo "テーブル 'settings' を作成しました。<br>";
    $sql_employee_field_settings = "CREATE TABLE `employee_field_settings` (`id` INT AUTO_INCREMENT PRIMARY KEY, `field_key` VARCHAR(100) NOT NULL UNIQUE, `label` VARCHAR(255) NOT NULL, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `is_protected` TINYINT(1) NOT NULL DEFAULT 0, `is_custom` TINYINT(1) NOT NULL DEFAULT 0, `display_order` INT NOT NULL DEFAULT 9999) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_employee_field_settings); echo "テーブル 'employee_field_settings' を作成しました。<br>";
    $sql_employee_custom_data = "CREATE TABLE `employee_custom_data` (`employee_id` INT NOT NULL, `field_id` INT NOT NULL, `value` TEXT, PRIMARY KEY (`employee_id`, `field_id`), FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE, FOREIGN KEY (`field_id`) REFERENCES `employee_field_settings`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_employee_custom_data); echo "テーブル 'employee_custom_data' を作成しました。<br>";
    $sql_list_options = "CREATE TABLE `list_options` (`id` INT AUTO_INCREMENT PRIMARY KEY, `category` VARCHAR(100) NOT NULL, `option_value` VARCHAR(255) NOT NULL, `display_order` INT DEFAULT 0, INDEX (`category`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_list_options); echo "テーブル 'list_options' を作成しました。<br>";
    $sql_audit_logs = "CREATE TABLE `audit_logs` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NULL, `employee_name` VARCHAR(255), `action` VARCHAR(255) NOT NULL, `details` TEXT, `ip_address` VARCHAR(45), `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_audit_logs); echo "テーブル 'audit_logs' を作成しました。<br>";
    
    // --- 初期データ投入 ---

    // (roles, fixed_roles, settings, employee_field_settings, list_optionsへのデータ投入は変更なし)
    $initial_roles_data = [ ['admin', 'システム管理者', 1, 1, 1, 1, 1, 1, 1], ['manager', '部門管理者・人事', 1, 1, 1, 1, 0, 0, 0], ['editor', '一般編集者', 1, 1, 0, 0, 0, 0, 0], ['viewer', '閲覧者', 1, 0, 0, 0, 0, 0, 0] ];
    $stmt_roles_insert = $pdo->prepare("INSERT INTO roles (name, description, can_view_employees, can_edit_employees, can_manage_departments, can_manage_fixed_roles, can_manage_roles, can_manage_settings, can_view_audit_logs) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($initial_roles_data as $r) { $stmt_roles_insert->execute($r); } echo "'roles' テーブルに初期権限グループを投入しました。<br>";
    $initial_fixed_roles = [ [1, '社長'], [2, '副社長'] ];
    $stmt_fixed_roles = $pdo->prepare("INSERT INTO fixed_roles (id, name) VALUES (?, ?)");
    foreach ($initial_fixed_roles as $role) { $stmt_fixed_roles->execute($role); } echo "'fixed_roles' テーブルに初期データを投入しました。<br>";
    $initial_settings = [ ['部署', '部署', 1, 0, 1], ['職員', '職員', 1, 0, 1], ['役職', '役職', 1, 0, 1], ['承認者', '承認者', 0, 0, 0], ['システム名', '総合管理システム', 0, 0, 0], ['会社名', '', 0, 0, 0], ['郵便番号', '', 0, 0, 0], ['住所', '', 0, 0, 0], ['電話番号', '', 0, 0, 0], ];
    $stmt_settings = $pdo->prepare("INSERT INTO settings (label, value, is_active, is_custom, is_protected) VALUES (?, ?, ?, ?, ?)");
    foreach ($initial_settings as $setting) { $stmt_settings->execute($setting); } echo "'settings' テーブルにテンプレートを投入しました。<br>";
    $employee_fields_catalog = [ ['name', '氏名', 1, 1, 10], ['name_kana', 'フリガナ', 1, 0, 20], ['status', '在籍状況', 1, 1, 30], ['department_id', '所属部署', 1, 1, 40], ['position', '役職', 1, 0, 50], ['job_title', '職種', 1, 0, 55], ['join_date', '入社日', 0, 0, 60], ['leave_date', '退職日', 0, 0, 70], ['employment_type', '雇用形態', 1, 0, 80], ['phone', '電話番号', 1, 0, 110], ['gender', '性別', 1, 0, 120], ['birth_date', '生年月日', 0, 0, 130], ['team', 'チーム', 0, 0, 140], ['supervisor_id', '直属の上司', 0, 0, 150], ['work_schedule_type', '勤務形態', 0, 0, 160], ['hourly_rate', '時給', 0, 0, 170], ['salary_grade', '等級', 0, 0, 180], ['overtime_rule_id', '残業ルールID', 0, 0, 190], ['leave_type', '休職理由', 0, 0, 200], ['leave_start_date', '休職開始日', 0, 0, 210], ['leave_end_date', '休職終了日', 0, 0, 220], ['leave_approval', '休職承認', 0, 0, 230], ['notes', '備考', 0, 0, 999], ['custom_field_1', 'カスタム項目1', 0, 0, 1000], ['custom_field_2', 'カスタム項目2', 0, 0, 1010], ];
    $stmt_efs = $pdo->prepare("INSERT INTO employee_field_settings (field_key, label, is_active, is_protected, display_order) VALUES (?, ?, ?, ?, ?)");
    foreach ($employee_fields_catalog as $field) { $stmt_efs->execute($field); } echo "'employee_field_settings' テーブルに項目カタログを投入しました。<br>";
    $initial_options = [ ['gender', '男性', 1], ['gender', '女性', 2], ['employment_type', '正社員', 1], ['employment_type', '契約社員', 2], ['employment_type', 'パートタイマー', 3], ['employment_type', 'アルバイト', 4], ['employment_type', '業務委託', 5], ['work_schedule_type', 'フルタイム', 1], ['work_schedule_type', '時短勤務', 2], ['work_schedule_type', 'フレックスタイム', 3], ];
    $stmt_options = $pdo->prepare("INSERT INTO list_options (category, option_value, display_order) VALUES (?, ?, ?)");
    foreach ($initial_options as $option) { $stmt_options->execute($option); } echo "'list_options' テーブルに初期データを投入しました。<br>";

    // ★★★ `employees` と `users` に初期管理者を「特別なID」で登録 ★★★
    $admin_employee_id = 999901; // 絶対に重複しないID
    $admin_name = 'システム管理者';
    $stmt_emp = $pdo->prepare("INSERT INTO employees (id, name, status) VALUES (?, ?, '在籍')");
    $stmt_emp->execute([$admin_employee_id, $admin_name]);
    echo "初期職員データ (システム管理者) を ID: {$admin_employee_id} で作成しました。<br>";

    $admin_username = 'admin'; // ログインIDは推測されにくいまま
    $admin_password = 'password';
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $admin_role_id = $pdo->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();

    $stmt_user = $pdo->prepare("INSERT INTO users (employee_id, username, password, role_id) VALUES (?, ?, ?, ?)");
    $stmt_user->execute([$admin_employee_id, $admin_username, $hashed_password, $admin_role_id]);
    echo "初期管理者アカウントを作成しました。<br>";
    echo "<b>職員ID:</b> " . htmlspecialchars($admin_employee_id) . "<br>";
    echo "<b>ログインID:</b> " . htmlspecialchars($admin_username) . "<br>";
    echo "<b>パスワード:</b> " . htmlspecialchars($admin_password) . "<br>";

    echo "<hr><strong>🎉 初期設定が正常に完了しました！</strong>";

} catch (PDOException $e) {
    die("エラー: ". $e->getMessage());
}
?>