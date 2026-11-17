<?php
function seed_database(PDO $pdo, bool $forceReset = false): void
{
    if ($forceReset) {
        $pdo->exec("DROP TABLE IF EXISTS slides");
        $pdo->exec("DROP TABLE IF EXISTS settings");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS slides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tag TEXT DEFAULT '',
            title TEXT DEFAULT '',
            subtitle TEXT DEFAULT '',
            badge TEXT DEFAULT '',
            specs TEXT DEFAULT '[]',
            image TEXT DEFAULT '',
            video_url TEXT DEFAULT '',
            accent TEXT DEFAULT '#3b82f6',
            type TEXT DEFAULT 'image',
            iframe_url TEXT DEFAULT '',
            action_label TEXT DEFAULT '',
            button_label TEXT DEFAULT '',
            sort_order INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1
        );
    ");

    $countSettings = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($countSettings === 0) {
        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (:key, :value)");
        $defaultSettings = [
            'site_title' => '',
            'header_badge' => '',
            'header_company' => '',
            'header_logo_url' => '',
            'slide_duration_seconds' => '8',
            'auto_slide_enabled' => '1',
            'date_locale' => 'en',
            'background_audio_url' => '',
            'bgm_playlist' => '',
            'default_audio_volume' => '1.0',
            'default_device_id' => 'SIGN-01',
            'seq_image' => '0',
            'seq_audio' => '0',
            'seq_video' => '0',
            'admin_password_hash' => password_hash('kiosign123', PASSWORD_DEFAULT)
        ];
        foreach ($defaultSettings as $key => $val) {
            $stmt->execute([':key' => $key, ':value' => (string) $val]);
        }
    }

    $countSlides = (int) $pdo->query("SELECT COUNT(*) FROM slides")->fetchColumn();
    if ($countSlides === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO slides (
                tag, title, subtitle, badge, specs, image, video_url, accent,
                type, iframe_url, action_label, button_label, sort_order, is_active
            ) VALUES (
                :tag, :title, :subtitle, :badge, :specs, :image, :video_url, :accent,
                :type, :iframe_url, :action_label, :button_label, :sort_order, :is_active
            )
        ");

        $defaultSlides = [
            [
                'tag' => 'Information',
                'title' => 'Welcome to Digital Information Display',
                'subtitle' => 'Digital signage media for presenting schedules, official announcements, and public services in real time.',
                'badge' => 'Official Notice',
                'specs' => json_encode(['Latest Updates', 'Prime Service', 'Accurate & Reliable'], JSON_UNESCAPED_UNICODE),
                'image' => '',
                'accent' => '#3b82f6',
                'type' => 'image',
                'iframe_url' => '',
                'action_label' => 'Information Center',
                'button_label' => 'Learn More',
                'sort_order' => 1,
                'is_active' => 1
            ]
        ];

        foreach ($defaultSlides as $slide) {
            $stmt->execute([
                ':tag' => $slide['tag'],
                ':title' => $slide['title'],
                ':subtitle' => $slide['subtitle'],
                ':badge' => $slide['badge'],
                ':specs' => $slide['specs'],
                ':image' => $slide['image'],
                ':video_url' => $slide['video_url'] ?? '',
                ':accent' => $slide['accent'],
                ':type' => $slide['type'],
                ':iframe_url' => $slide['iframe_url'],
                ':action_label' => $slide['action_label'],
                ':button_label' => $slide['button_label'],
                ':sort_order' => $slide['sort_order'],
                ':is_active' => $slide['is_active']
            ]);
        }
    }
}
