
add_shortcode('certificate-form', function () {
    $submit_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['certificate_form_submit'])) {
        $gas_url = 'https://script.google.com/macros/s/AKfycbw-z6zP0K774d9jFyxE5noYQcelOfNJxCGxe71C312EFEL-_49yl5yzfqt7KjKFdxxs6A/exec';

        $payload = certificate_sanitize_post_data($_POST);
        unset($payload['certificate_form_submit']);
        $payload['申請種別'] = '証明書発行申請';

        $request_args = [
            'headers' => [
                'User-Agent' => 'WordPress Certificate Form',
            ],
            'body' => [
                'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            ],
            'timeout' => 30,
            'redirection' => 0,
        ];

        $response = wp_remote_post($gas_url, $request_args);
        $redirect_url = '';

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $location = wp_remote_retrieve_header($response, 'location');

            if ($code >= 300 && $code < 400 && $location) {
                $redirect_url = $location;
                $response = wp_remote_get($location, [
                    'headers' => [
                        'User-Agent' => 'WordPress Certificate Form',
                    ],
                    'timeout' => 30,
                    'redirection' => 5,
                ]);
            }
        }

        if (is_wp_error($response)) {
            $submit_message = '<div class="certificate-submit-message error">送信失敗: WordPress HTTP Error<br>' . esc_html($response->get_error_message()) . '</div>';
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code >= 200 && $code < 300) {
                $decoded_body = json_decode($body, true);

                if (is_array($decoded_body) && isset($decoded_body['ok']) && $decoded_body['ok'] === true) {
                    $submit_message = '<div class="certificate-submit-message success">送信が完了しました。</div>';
                } elseif (is_array($decoded_body) && isset($decoded_body['ok']) && $decoded_body['ok'] === false) {
                    $submit_message = '<div class="certificate-submit-message error">GAS処理失敗:<br>' . esc_html($body) . '</div>';
                } else {
                    $submit_message = '<div class="certificate-submit-message error">GAS処理失敗: JSON応答を確認できません。<br><pre style="white-space:pre-wrap;">' . esc_html(mb_substr($body, 0, 1500)) . '</pre></div>';
                }
            } else {
                $headers = wp_remote_retrieve_headers($response);
                $debug_url = $redirect_url ? $redirect_url : $gas_url;
                $submit_message = '<div class="certificate-submit-message error">送信失敗: HTTP ' . esc_html($code) . '<br><pre style="white-space:pre-wrap;">URL: ' . esc_html(mb_substr($debug_url, 0, 500)) . "\n\n" . esc_html(print_r($headers, true)) . '</pre><br>' . esc_html(mb_substr($body, 0, 1000)) . '</div>';
            }
        }
    }

    ob_start();
    ?>

    <style>
        .certificate-form {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            border: 1px solid #d7dde8;
            border-radius: 12px;
            background: #f8fafc;
            box-shadow: 0 18px 45px rgba(30, 41, 59, 0.12);
            color: #172033;
            position: relative;
            overflow: hidden;
        }
        .certificate-form fieldset {
            margin: 24px 0;
            padding: 22px;
            border: 1px solid #dbe2ec;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.06);
        }
        .certificate-form legend {
            font-weight: bold;
            padding: 0 8px;
            color: #0f4c81;
        }
        .certificate-form label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 14px;
            font-weight: bold;
            color: #1f2937;
        }
        .certificate-form input,
        .certificate-form select,
        .certificate-form textarea {
            width: 100%;
            padding: 11px 12px;
            margin-top: 6px;
            box-sizing: border-box;
            border: 1px solid #cfd8e5;
            border-radius: 7px;
            background: #fff;
            color: #172033;
            font-size: 15px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }
        .certificate-form textarea {
            min-height: 100px;
            resize: vertical;
        }
        .certificate-form input:focus,
        .certificate-form select:focus,
        .certificate-form textarea:focus {
            outline: none;
            border-color: #1f78b4;
            box-shadow: 0 0 0 4px rgba(31, 120, 180, 0.14);
            background: #fbfdff;
        }

        .certificate-form .form-step,
        .certificate-form .hidden-section {
            display: none;
        }
        .certificate-form .form-step.active {
            display: block;
            animation: certificateStepSlideIn 0.34s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .certificate-form .form-step.slide-back.active {
            animation: certificateStepSlideBack 0.34s cubic-bezier(0.22, 1, 0.36, 1);
        }
        @keyframes certificateStepSlideIn {
            from {
                opacity: 0;
                transform: translateX(46px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
        @keyframes certificateStepSlideBack {
            from {
                opacity: 0;
                transform: translateX(-46px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        .certificate-form .step-nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
            align-items: center;
        }
        .certificate-form button {
            padding: 12px 28px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            background: #0f6ea8;
            color: #fff;
            font-weight: bold;
            transition: transform 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
            box-shadow: 0 8px 18px rgba(15, 110, 168, 0.25);
        }
        .certificate-form button:hover {
            background: #0b5c8d;
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15, 110, 168, 0.28);
        }
        .certificate-form button:active {
            transform: translateY(0);
            box-shadow: 0 5px 12px rgba(15, 110, 168, 0.22);
        }
        .certificate-form button:disabled {
            cursor: wait;
            opacity: 0.72;
            transform: none;
        }
        .certificate-form .secondary-button {
            background: #eef3f8;
            color: #164b73;
            border: 1px solid #bed0df;
            box-shadow: 0 5px 14px rgba(30, 41, 59, 0.08);
        }
        .certificate-form .secondary-button:hover {
            background: #e1ebf3;
            color: #0f3f64;
        }

        .certificate-form .step-indicator {
            margin-bottom: 18px;
            font-weight: bold;
            color: #164b73;
        }
        .certificate-form .progress-shell {
            margin-bottom: 24px;
            padding: 14px 16px;
            border: 1px solid #dbe2ec;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(30, 41, 59, 0.06);
        }
        .certificate-form .progress-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            color: #334155;
            font-weight: bold;
            font-size: 14px;
        }
        .certificate-form .progress-track {
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e7edf4;
        }
        .certificate-form .progress-fill {
            display: block;
            height: 100%;
            width: 33%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f6ea8, #25a36f);
            transition: width 0.34s ease;
        }

        .certificate-form .radio-card-group,
        .certificate-form .checkbox-card-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }
        .certificate-form .radio-card-group label,
        .certificate-form .checkbox-card-group label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 0;
            padding: 9px 12px;
            border: 1px solid #cfd8e5;
            border-radius: 7px;
            background: #f8fafc;
            font-weight: bold;
            color: #334155;
        }
        .certificate-form .radio-card-group input,
        .certificate-form .checkbox-card-group input {
            width: auto;
            margin-top: 0;
        }
        .certificate-form .purpose-card-group {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .certificate-form .purpose-card {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 0;
            padding: 18px;
            border: 1px solid #c9d8e6;
            border-radius: 10px;
            background: #f7fbff;
            color: #164b73;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.16s ease, background 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        }
        .certificate-form .purpose-card:hover {
            background: #e7f3fb;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 110, 168, 0.12);
        }
        .certificate-form .purpose-card.is-selected {
            border-color: #0f6ea8;
            background: #e7f3fb;
            color: #0f3f64;
            box-shadow: inset 0 0 0 1px rgba(15, 110, 168, 0.2), 0 8px 18px rgba(15, 110, 168, 0.12);
        }
        .certificate-form .purpose-card.is-selected::after {
            content: "選択済み";
            color: #116b35;
            font-size: 12px;
            font-weight: bold;
        }
        .certificate-form .purpose-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .certificate-form .purpose-card-title {
            font-size: 17px;
            line-height: 1.35;
        }
        .certificate-form .purpose-card-description {
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
            font-weight: bold;
        }

        .certificate-form .document-table-wrap {
            overflow-x: auto;
            margin-top: 14px;
            border: 1px solid #dbe2ec;
            border-radius: 10px;
            background: #fff;
        }
        .certificate-form table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }
        .certificate-form th,
        .certificate-form td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }
        .certificate-form th {
            background: #f1f6fb;
            color: #164b73;
            font-weight: bold;
        }
        .certificate-form tr:last-child td {
            border-bottom: none;
        }
        .certificate-form .doc-qty-input {
            max-width: 120px;
        }
        .certificate-form .price-cell,
        .certificate-form .subtotal-cell {
            white-space: nowrap;
            font-weight: bold;
        }
        .certificate-form .description-cell {
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }
        .certificate-form .total-panel {
            margin-top: 16px;
            padding: 16px 18px;
            border: 1px solid #9ad4b0;
            border-radius: 10px;
            background: #e8f6ee;
            color: #116b35;
            font-weight: bold;
            text-align: right;
            font-size: 18px;
        }
        .certificate-form .input-counter {
            margin-top: 5px;
            text-align: right;
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
            line-height: 1;
        }
        .certificate-form .input-counter.is-complete {
            color: #116b35;
        }
        .certificate-form .input-counter.is-error {
            color: #b91c1c;
        }
        .certificate-form .help-tip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e8f2fb;
            color: #0f6ea8;
            border: 1px solid #b8d7ec;
            font-size: 12px;
            line-height: 1;
            cursor: help;
        }
        .certificate-form .help-tip::after {
            content: attr(data-tip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            z-index: 10;
            width: max-content;
            max-width: 260px;
            padding: 8px 10px;
            border-radius: 7px;
            background: #172033;
            color: #fff;
            font-size: 12px;
            font-weight: normal;
            line-height: 1.45;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, 5px);
            transition: opacity 0.16s ease, transform 0.16s ease;
            white-space: normal;
        }
        .certificate-form .help-tip:hover::after,
        .certificate-form .help-tip:focus::after {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        .certificate-form .branch-note {
            margin-top: 12px;
            padding: 12px 14px;
            border: 1px solid #dbe2ec;
            border-radius: 8px;
            background: #fff;
            color: #475569;
            font-weight: bold;
        }

        .certificate-form .confirm-table {
            min-width: 100%;
        }
        .certificate-form .confirm-table th {
            width: 34%;
        }
        .certificate-form .confirm-table td {
            white-space: pre-wrap;
        }
        .certificate-form .confirm-total-row th,
        .certificate-form .confirm-total-row td {
            background: #e8f6ee;
            color: #116b35;
            font-weight: bold;
        }

        .certificate-form.is-submitting .certificate-submit-overlay {
            display: flex;
        }
        .certificate-submit-overlay {
            position: fixed;
            inset: 0;
            z-index: 30;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, 0.34);
            backdrop-filter: blur(3px);
        }
        .certificate-submit-panel {
            width: min(520px, 100%);
            padding: 40px;
            border-radius: 16px;
            background: #fff;
            text-align: center;
            box-shadow: 0 18px 45px rgba(30, 41, 59, 0.18);
            color: #172033;
            font-weight: bold;
            font-size: 18px;
            animation: certificateSubmitPanelIn 0.22s ease-out;
        }
        .certificate-submit-panel.success {
            color: #116b35;
        }
        .certificate-submit-panel.error {
            color: #9b1c1c;
        }
        .certificate-spinner {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border: 7px solid #dbe8f2;
            border-top-color: #0f6ea8;
            border-radius: 50%;
            animation: certificateSpin 0.8s linear infinite;
        }
        .certificate-submit-check {
            display: none;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #25a36f;
            color: #fff;
            font-size: 42px;
            animation: certificateCheckPop 0.34s ease-out;
        }
        .certificate-submit-overlay.is-success .certificate-spinner,
        .certificate-submit-overlay.is-error .certificate-spinner {
            display: none;
        }
        .certificate-submit-overlay.is-success .certificate-submit-check {
            display: inline-flex;
        }

        .certificate-submit-message {
            max-width: 900px;
            margin: 16px auto;
            padding: 14px 18px;
            border-radius: 8px;
            font-weight: bold;
            animation: certificateCardIn 0.3s ease-out;
        }
        .certificate-submit-message.success::before {
            content: "✓";
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            margin-right: 9px;
            border-radius: 50%;
            background: #25a36f;
            color: #fff;
            animation: certificateCheckPop 0.34s ease-out;
        }
        .certificate-submit-message.success {
            background: #e8f6ee;
            color: #116b35;
            border: 1px solid #9ad4b0;
        }
        .certificate-submit-message.error {
            background: #fdecec;
            color: #9b1c1c;
            border: 1px solid #f3aaaa;
        }
        .certificate-form-version {
            margin-top: 12px;
            text-align: right;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1;
        }

        @keyframes certificateSpin {
            to {
                transform: rotate(360deg);
            }
        }
        @keyframes certificateSubmitPanelIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        @keyframes certificateCardIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes certificateCheckPop {
            from {
                transform: scale(0.55);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @media (max-width: 600px) {
            .certificate-form {
                padding: 16px;
            }
            .certificate-form .step-nav {
                flex-direction: column;
                align-items: stretch;
            }
            .certificate-form button {
                width: 100%;
            }
            .certificate-form .total-panel {
                text-align: left;
            }
            .certificate-form .purpose-card-group {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php echo $submit_message; ?>

    <form class="certificate-form" method="post">
        <div class="progress-shell" aria-label="入力進捗">
            <div class="progress-meta">
                <span id="certificate_progress_step_text">STEP 1 / 3</span>
                <span id="certificate_progress_percent_text">33%</span>
            </div>
            <div class="progress-track" aria-hidden="true">
                <span class="progress-fill" id="certificate_progress_fill"></span>
            </div>
        </div>

        <section class="form-step active" data-step="1">
            <div class="step-indicator">STEP 1 / 3　基本情報</div>

            <fieldset>
                <legend>基本情報</legend>

                <label>学籍番号</label>
                <input type="text" id="certificate_student_number" name="学籍番号" inputmode="numeric" pattern="[0-9]{7}" maxlength="7" title="7桁の半角数字で入力してください" autocomplete="off" required>
                <div id="certificate_student_number_counter" class="input-counter">0 / 7</div>

                <label>名前</label>
                <input type="text" name="名前" placeholder="例：山田 太郎" required>

                <label>生年月日</label>
                <input type="date" name="生年月日" required>

                <label>メールアドレス</label>
                <input type="email" name="メールアドレス" placeholder="例：student@example.com" required>

                <label>電話番号</label>
                <input type="tel" id="certificate_phone_number" name="電話番号" placeholder="例：090-1234-5678" inputmode="numeric" pattern="0[0-9]{1,4}-[0-9]{1,4}-[0-9]{3,4}" title="電話番号を正しく入力してください（例：090-1234-5678）" autocomplete="tel" required>
                <input type="hidden" id="certificate_phone_number_digits" name="電話番号（数字のみ）" value="">

                <label>学校名</label>
                <select id="certificate_school_name" name="学校名" required>
                    <option value="">選択してください</option>
                    <option>広島コンピュータ専門学校</option>
                    <option>広島会計学院ビジネス専門学校</option>
                    <option>広島外語専門学校</option>
                    <option>広島美容専門学校</option>
                    <option>広島情報ビジネス専門学校</option>
                    <option>広島公務員専門学校</option>
                </select>

                <div id="certificate_hirocon_only_fields" class="hidden-section">
                    <label>コース名</label>
                    <select id="certificate_course_name_select" name="コース名">
                        <option value="">選択してください</option>
                        <option>システムエンジニア</option>
                        <option>ネットワークエンジニア</option>
                        <option>ゲームクリエーター</option>
                        <option>情報処理プログラム</option>
                        <option>オフィスビジネス</option>
                        <option>webデザイン</option>
                        <option>ゲームCG</option>
                        <option>ネットワークセキュリティ</option>
                        <option>イラスト</option>
                        <option>グラフィックデザイン</option>
                        <option>音響技術</option>
                        <option>CGクリエーター</option>
                    </select>

                    <label>担任</label>
                    <select id="certificate_teacher_name_select" name="担任">
                        <option value="">選択してください</option>
                        <option>山岡</option>
                        <option>森本</option>
                        <option>南</option>
                        <option>シゴレフ</option>
                        <option>竹田</option>
                        <option>新川</option>
                    </select>
                </div>

                <div id="certificate_other_school_fields">
                    <label>コース名</label>
                    <select id="certificate_hiroka_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>医療事務コース</option>
                        <option>フラワーデザイナーコース</option>
                        <option>経理財務コース</option>
                        <option>税理士マスターコース</option>
                        <option>税理士コース</option>
                    </select>
                    <select id="certificate_gaigo_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>総合英語コース</option>
                        <option>海外留学コース</option>
                        <option>国内大学編入コース</option>
                        <option>エアラインコース</option>
                        <option>ホテルコース</option>
                        <option>国際ビジネスコース</option>
                    </select>
                    <select id="certificate_biyo_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>トータルビューティ科 ヘアメイクコース</option>
                        <option>トータルビューティ科 メイクアップコース</option>
                        <option>トータルビューティ科 ネイルコース</option>
                        <option>トータルビューティ科 エステティックコース</option>
                    </select>
                    <select id="certificate_hjb_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>医療秘書コース</option>
                        <option>ブライダルコーディネーターコース</option>
                        <option>ペットビジネスコース</option>
                        <option>ペットケア＆トレーニングコース</option>
                        <option>情報ビジネスコース</option>
                        <option>販売ビジネスコース</option>
                    </select>
                    <select id="certificate_uhk_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>公務員科</option>
                        <option>公務員速成科</option>
                    </select>

                    <label>担任</label>
                    <input type="text" id="certificate_teacher_name_text" name="担任">
                </div>
            </fieldset>

            <div class="step-nav">
                <button type="button" data-next-step="2">次へ</button>
            </div>
        </section>

        <section class="form-step" data-step="2">
            <div class="step-indicator">STEP 2 / 3　証明書選択</div>

            <fieldset>
                <legend>使用目的</legend>

                <label>使用目的</label>
                <div class="purpose-card-group" aria-label="使用目的">
                    <label class="purpose-card" data-purpose-card="就職用">
                        <input type="radio" name="使用目的" value="就職用" required>
                        <span class="purpose-card-title">就職用</span>
                        <span class="purpose-card-description">就職活動で提出する証明書を申し込みます。</span>
                    </label>
                    <label class="purpose-card" data-purpose-card="その他">
                        <input type="radio" name="使用目的" value="その他" required>
                        <span class="purpose-card-title">その他</span>
                        <span class="purpose-card-description">在学証明書・卒業証明書などを申し込みます。</span>
                    </label>
                </div>
            </fieldset>

            <fieldset id="certificate_documents_fields" class="hidden-section">
                <legend>証明書一覧</legend>

                <div id="certificate_employment_note" class="branch-note hidden-section">
                    以下に掲載されていない証明書を希望の場合は、事務局まで来てください。
                </div>

                <div class="document-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>証明書</th>
                                <th>手数料</th>
                                <th>発行日数</th>
                                <th>枚数</th>
                                <th>小計</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-purpose-row="就職用">
                                <td>
                                    <strong>成績証明書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="成績証明書" name="就職用_成績証明書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>
                            <tr data-purpose-row="就職用">
                                <td>
                                    <strong>卒業見込書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="卒業見込書" name="就職用_卒業見込書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>
                            <tr data-purpose-row="就職用">
                                <td>
                                    <strong>健康診断書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="健康診断書" name="就職用_健康診断書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>

                            <tr data-purpose-row="その他">
                                <td>
                                    <strong>在学証明書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="在学証明書" name="その他_在学証明書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>
                            <tr data-purpose-row="その他">
                                <td>
                                    <strong>成績証明書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="成績証明書" name="その他_成績証明書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>
                            <tr data-purpose-row="その他">
                                <td>
                                    <strong>卒業見込書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="卒業見込書" name="その他_卒業見込書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>
                            <tr data-purpose-row="その他">
                                <td>
                                    <strong>卒業証明書</strong>
                                    <div class="description-cell">手数料200円　発行日数2日</div>
                                </td>
                                <td class="price-cell">200円</td>
                                <td>2日</td>
                                <td><input class="doc-qty-input" type="number" min="0" step="1" value="0" data-price="200" data-doc-label="卒業証明書" name="その他_卒業証明書_枚数"></td>
                                <td class="subtotal-cell">0円</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="total-panel">
                    合計金額：<span id="certificate_total_amount_text">0円</span>
                </div>

                <input type="hidden" id="certificate_total_amount" name="合計金額" value="0円">
                <input type="hidden" id="certificate_selected_documents" name="選択した証明書" value="">
            </fieldset>

            <fieldset id="certificate_employment_fields" class="hidden-section">
                <legend>就職用 詳細</legend>

                <label>受験先企業名</label>
                <input type="text" name="受験先企業名">

                <label>担任名</label>
                <input type="text" name="担任名">

                <label>書類提出日</label>
                <input type="date" name="書類提出日">
            </fieldset>

            <fieldset id="certificate_other_fields" class="hidden-section">
                <legend>その他 詳細</legend>

                <label>使用目的、提出先など</label>
                <input type="text" name="使用目的、提出先など">
            </fieldset>

            <fieldset id="certificate_receive_fields" class="hidden-section">
                <legend>受取方法・備考</legend>

                <label>受取方法</label>
                <div class="checkbox-card-group">
                    <label><input type="checkbox" name="受取方法[]" value="紙（窓口受取）">紙（窓口受取）</label>
                    <label><input type="checkbox" name="受取方法[]" value="PDF（メール送付）">PDF（メール送付）</label>
                </div>

                <label>備考</label>
                <textarea name="備考"></textarea>
            </fieldset>

            <div class="step-nav">
                <button type="button" class="secondary-button" data-prev-step="1">戻る</button>
                <button type="button" id="certificate_go_confirm_step">次へ</button>
            </div>
        </section>

        <section class="form-step" data-step="3">
            <div class="step-indicator">STEP 3 / 3　確認・送信</div>

            <fieldset>
                <legend>入力内容確認</legend>
                <div id="certificate_confirm_output" class="document-table-wrap"></div>
            </fieldset>

            <div class="step-nav">
                <button type="button" class="secondary-button" data-prev-step="2">戻る</button>
                <button type="submit" name="certificate_form_submit">送信</button>
            </div>
        </section>

        <div class="certificate-submit-overlay" role="status" aria-live="polite">
            <div class="certificate-submit-panel">
                <div class="certificate-spinner" aria-hidden="true"></div>
                <div class="certificate-submit-check" aria-hidden="true">✓</div>
                <div class="certificate-submit-status">フォームを送信しています。<br>少々お待ちください。</div>
            </div>
        </div>

        <div class="certificate-form-version">シゴレフ　エドワード　ver. 1.0</div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.certificate-form');
        if (!form) return;

        const steps = form.querySelectorAll('.form-step');
        const progressFill = document.getElementById('certificate_progress_fill');
        const progressStepText = document.getElementById('certificate_progress_step_text');
        const progressPercentText = document.getElementById('certificate_progress_percent_text');

        const studentNumberInput = document.getElementById('certificate_student_number');
        const studentNumberCounter = document.getElementById('certificate_student_number_counter');
        const phoneNumberInput = document.getElementById('certificate_phone_number');
        const phoneNumberDigitsInput = document.getElementById('certificate_phone_number_digits');
        const schoolName = document.getElementById('certificate_school_name');
        const hiroconOnlyFields = document.getElementById('certificate_hirocon_only_fields');
        const otherSchoolFields = document.getElementById('certificate_other_school_fields');
        const courseNameSelect = document.getElementById('certificate_course_name_select');
        const hirokaCourseNameSelect = document.getElementById('certificate_hiroka_course_name_select');
        const gaigoCourseNameSelect = document.getElementById('certificate_gaigo_course_name_select');
        const biyoCourseNameSelect = document.getElementById('certificate_biyo_course_name_select');
        const hjbCourseNameSelect = document.getElementById('certificate_hjb_course_name_select');
        const uhkCourseNameSelect = document.getElementById('certificate_uhk_course_name_select');
        const teacherNameSelect = document.getElementById('certificate_teacher_name_select');
        const teacherNameText = document.getElementById('certificate_teacher_name_text');

        const purposeRadios = form.querySelectorAll('input[name="使用目的"]');
        const purposeCards = form.querySelectorAll('[data-purpose-card]');
        const purposeRows = form.querySelectorAll('[data-purpose-row]');
        const documentsFields = document.getElementById('certificate_documents_fields');
        const receiveFields = document.getElementById('certificate_receive_fields');
        const employmentNote = document.getElementById('certificate_employment_note');
        const employmentFields = document.getElementById('certificate_employment_fields');
        const otherFields = document.getElementById('certificate_other_fields');

        const qtyInputs = form.querySelectorAll('.doc-qty-input');
        const totalAmountText = document.getElementById('certificate_total_amount_text');
        const totalAmountInput = document.getElementById('certificate_total_amount');
        const selectedDocumentsInput = document.getElementById('certificate_selected_documents');
        const goConfirmButton = document.getElementById('certificate_go_confirm_step');
        const confirmOutput = document.getElementById('certificate_confirm_output');

        const submitOverlay = form.querySelector('.certificate-submit-overlay');
        const submitPanel = form.querySelector('.certificate-submit-panel');
        const submitStatus = form.querySelector('.certificate-submit-status');

        let currentStepNumber = 1;
        const totalSteps = steps.length;

        function showStep(stepNumber) {
            const nextStepNumber = Number(stepNumber);
            const isBack = nextStepNumber < currentStepNumber;

            steps.forEach(function (step) {
                step.classList.remove('active', 'slide-back');

                if (step.dataset.step === String(stepNumber)) {
                    if (isBack) {
                        step.classList.add('slide-back');
                    }
                    step.classList.add('active');
                }
            });

            currentStepNumber = nextStepNumber;
            updateProgress();
            window.scrollTo({ top: form.offsetTop, behavior: 'smooth' });
        }

        function updateProgress() {
            const percent = Math.round((currentStepNumber / totalSteps) * 100);
            progressFill.style.width = percent + '%';
            progressStepText.textContent = 'STEP ' + currentStepNumber + ' / ' + totalSteps;
            progressPercentText.textContent = percent + '%';
        }

        function setSectionVisibility(section, isVisible) {
            section.style.display = isVisible ? 'block' : 'none';

            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !isVisible;

                if (!isVisible) {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = false;
                    } else {
                        field.value = '';
                    }
                }
            });
        }

        function configureSchoolCourseSelect(select, isVisible) {
            select.required = isVisible;
            select.disabled = !isVisible;
            select.style.display = isVisible ? '' : 'none';

            if (!isVisible) {
                select.value = '';
            }
        }

        function updateHiroconFields() {
            const isHirocon = schoolName.value === '広島コンピュータ専門学校';
            const isHiroka = schoolName.value === '広島会計学院ビジネス専門学校';
            const isGaigo = schoolName.value === '広島外語専門学校';
            const isBiyo = schoolName.value === '広島美容専門学校';
            const isHjb = schoolName.value === '広島情報ビジネス専門学校';
            const isUhk = schoolName.value === '広島公務員専門学校';

            setSectionVisibility(hiroconOnlyFields, isHirocon);
            setSectionVisibility(otherSchoolFields, isHirocon ? false : Boolean(schoolName.value));

            courseNameSelect.required = isHirocon;
            teacherNameSelect.required = isHirocon;
            courseNameSelect.disabled = !isHirocon;
            teacherNameSelect.disabled = !isHirocon;

            configureSchoolCourseSelect(hirokaCourseNameSelect, isHiroka);
            configureSchoolCourseSelect(gaigoCourseNameSelect, isGaigo);
            configureSchoolCourseSelect(biyoCourseNameSelect, isBiyo);
            configureSchoolCourseSelect(hjbCourseNameSelect, isHjb);
            configureSchoolCourseSelect(uhkCourseNameSelect, isUhk);

            teacherNameText.required = !isHirocon;
            teacherNameText.disabled = isHirocon;

            if (isHirocon) {
                teacherNameText.value = '';
            } else {
                courseNameSelect.value = '';
                teacherNameSelect.value = '';
            }
        }

        function keepDigitsOnly(field, maxLength) {
            const digitsOnly = field.value.replace(/[^0-9]/g, '');
            field.value = maxLength ? digitsOnly.slice(0, maxLength) : digitsOnly;
        }

        function updateStudentNumberCounter() {
            keepDigitsOnly(studentNumberInput, 7);

            const currentLength = studentNumberInput.value.length;
            studentNumberCounter.textContent = currentLength + ' / 7';
            studentNumberCounter.classList.toggle('is-complete', currentLength === 7);
            studentNumberCounter.classList.toggle('is-error', currentLength > 0 && currentLength < 7);
        }

        function formatJapanesePhoneNumber(digits) {
            const cleanDigits = digits.replace(/[^0-9]/g, '').slice(0, 11);

            if (cleanDigits.length <= 3) {
                return cleanDigits;
            }

            if (cleanDigits.startsWith('0120') || cleanDigits.startsWith('0800')) {
                if (cleanDigits.length <= 7) {
                    return cleanDigits.slice(0, 4) + '-' + cleanDigits.slice(4);
                }
                return cleanDigits.slice(0, 4) + '-' + cleanDigits.slice(4, 7) + '-' + cleanDigits.slice(7);
            }

            if (cleanDigits.length === 10) {
                if (cleanDigits.startsWith('03') || cleanDigits.startsWith('06')) {
                    return cleanDigits.slice(0, 2) + '-' + cleanDigits.slice(2, 6) + '-' + cleanDigits.slice(6);
                }
                return cleanDigits.slice(0, 3) + '-' + cleanDigits.slice(3, 6) + '-' + cleanDigits.slice(6);
            }

            if (cleanDigits.length >= 11) {
                return cleanDigits.slice(0, 3) + '-' + cleanDigits.slice(3, 7) + '-' + cleanDigits.slice(7);
            }

            return cleanDigits.slice(0, 3) + '-' + cleanDigits.slice(3);
        }

        function updatePhoneNumberInput() {
            const digitsOnly = phoneNumberInput.value.replace(/[^0-9]/g, '').slice(0, 11);
            phoneNumberInput.value = formatJapanesePhoneNumber(digitsOnly);
            phoneNumberDigitsInput.value = digitsOnly;

            if (digitsOnly.length >= 10 && digitsOnly.length <= 11) {
                phoneNumberInput.setCustomValidity('');
            } else {
                phoneNumberInput.setCustomValidity('電話番号は10桁または11桁で入力してください。');
            }
        }

        function getSelectedPurpose() {
            const checked = form.querySelector('input[name="使用目的"]:checked');
            return checked ? checked.value : '';
        }

        function updatePurposeView() {
            const purpose = getSelectedPurpose();

            purposeCards.forEach(function (card) {
                card.classList.toggle('is-selected', card.dataset.purposeCard === purpose);
            });

            purposeRows.forEach(function (row) {
                const isVisible = row.dataset.purposeRow === purpose;
                row.style.display = isVisible ? 'table-row' : 'none';

                row.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !isVisible;

                    if (!isVisible) {
                        input.value = 0;
                    }
                });
            });

            setSectionVisibility(documentsFields, Boolean(purpose));
            setSectionVisibility(receiveFields, Boolean(purpose));
            setSectionVisibility(employmentNote, purpose === '就職用');
            setSectionVisibility(employmentFields, purpose === '就職用');
            setSectionVisibility(otherFields, purpose === 'その他');

            updateTotal();
        }

        function updateTotal() {
            let total = 0;
            const selectedDocuments = [];

            qtyInputs.forEach(function (input) {
                const row = input.closest('tr');
                const subtotalCell = row.querySelector('.subtotal-cell');

                if (input.disabled) {
                    subtotalCell.textContent = '0円';
                    return;
                }

                const qty = Math.max(0, Number(input.value || 0));
                const price = Number(input.dataset.price || 0);
                const subtotal = qty * price;

                subtotalCell.textContent = subtotal.toLocaleString() + '円';
                total += subtotal;

                if (qty > 0) {
                    selectedDocuments.push(input.dataset.docLabel + '：' + qty + '枚（' + subtotal.toLocaleString() + '円）');
                }
            });

            totalAmountText.textContent = total.toLocaleString() + '円';
            totalAmountInput.value = total.toLocaleString() + '円';
            selectedDocumentsInput.value = selectedDocuments.join('\n');
        }

        function validateCurrentStep(currentStep) {
            updateTotal();

            const activeStep = form.querySelector('.form-step[data-step="' + currentStep + '"]');
            const requiredFields = activeStep.querySelectorAll('[required]');

            for (const field of requiredFields) {
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }

            return true;
        }

        function validateStep2() {
            if (!validateCurrentStep(2)) {
                return false;
            }

            const hasDocument = Array.from(qtyInputs).some(function (input) {
                return !input.disabled && Number(input.value || 0) > 0;
            });

            if (!hasDocument) {
                alert('証明書を1つ以上選択してください。');
                return false;
            }

            const hasReceiveMethod = form.querySelectorAll('input[name="受取方法[]"]:checked').length > 0;

            if (!hasReceiveMethod) {
                alert('受取方法を1つ以上選択してください。');
                return false;
            }

            return true;
        }

        function buildConfirmOutput() {
            updateTotal();

            const rows = [];

            function addRow(label, value) {
                if (value === null || value === undefined) return;

                const cleanValue = String(value).trim();
                if (!cleanValue) return;

                rows.push({
                    label: label,
                    value: cleanValue
                });
            }

            addRow('学籍番号', getFieldValue('学籍番号'));
            addRow('名前', getFieldValue('名前'));
            addRow('生年月日', getFieldValue('生年月日'));
            addRow('メールアドレス', getFieldValue('メールアドレス'));
            addRow('電話番号', getFieldValue('電話番号'));
            addRow('電話番号（数字のみ）', getFieldValue('電話番号（数字のみ）'));
            addRow('学校名', getFieldValue('学校名'));
            addRow('コース名', getFieldValue('コース名'));
            addRow('担任', getFieldValue('担任'));
            addRow('使用目的', getFieldValue('使用目的'));
            addRow('選択した証明書', selectedDocumentsInput.value);
            addRow('受験先企業名', getFieldValue('受験先企業名'));
            addRow('担任名', getFieldValue('担任名'));
            addRow('書類提出日', getFieldValue('書類提出日'));
            addRow('使用目的、提出先など', getFieldValue('使用目的、提出先など'));
            addRow('受取方法', getCheckedValues('受取方法[]').join('\n'));
            addRow('備考', getFieldValue('備考'));
            addRow('合計金額', totalAmountInput.value);

            let html = '<table class="confirm-table"><tbody>';

            rows.forEach(function (row) {
                const isTotal = row.label === '合計金額';
                html += '<tr' + (isTotal ? ' class="confirm-total-row"' : '') + '>';
                html += '<th>' + escapeHtml(row.label) + '</th>';
                html += '<td>' + escapeHtml(row.value) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            confirmOutput.innerHTML = html;
        }

        function getFieldValue(name) {
            const fields = form.querySelectorAll('[name="' + cssEscape(name) + '"]');

            for (const field of fields) {
                if (field.disabled) continue;

                if (field.type === 'radio') {
                    if (field.checked) return field.value;
                    continue;
                }

                if (field.type === 'checkbox') {
                    if (field.checked) return field.value;
                    continue;
                }

                return field.value;
            }

            return '';
        }

        function getCheckedValues(name) {
            return Array.from(form.querySelectorAll('[name="' + cssEscape(name) + '"]:checked'))
                .filter(function (field) {
                    return !field.disabled;
                })
                .map(function (field) {
                    return field.value;
                });
        }

        function cssEscape(value) {
            if (window.CSS && CSS.escape) {
                return CSS.escape(value);
            }

            return String(value).replace(/"/g, '\\"');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function resetAppForm() {
            form.reset();
            confirmOutput.innerHTML = '';
            studentNumberInput.value = '';
            phoneNumberInput.value = '';
            phoneNumberDigitsInput.value = '';
            updateStudentNumberCounter();
            updatePhoneNumberInput();
            updateHiroconFields();
            updatePurposeView();
            updateTotal();
            showStep(1);
        }

        function setSubmitState(state, message) {
            submitOverlay.classList.remove('is-success', 'is-error');
            submitPanel.classList.remove('success', 'error');

            if (state) {
                submitOverlay.classList.add('is-' + state);
                submitPanel.classList.add(state);
            }

            submitStatus.innerHTML = message;
        }

        function unlockSubmitAfterError() {
            window.setTimeout(function () {
                form.classList.remove('is-submitting');
            }, 2600);
        }

        form.querySelectorAll('[data-next-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                const currentStep = button.closest('.form-step').dataset.step;

                if (!validateCurrentStep(currentStep)) {
                    return;
                }

                showStep(button.dataset.nextStep);
            });
        });

        form.querySelectorAll('[data-prev-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                showStep(button.dataset.prevStep);
            });
        });

        studentNumberInput.addEventListener('input', updateStudentNumberCounter);
        studentNumberInput.addEventListener('paste', function () {
            window.setTimeout(updateStudentNumberCounter, 0);
        });
        phoneNumberInput.addEventListener('input', updatePhoneNumberInput);
        phoneNumberInput.addEventListener('paste', function () {
            window.setTimeout(updatePhoneNumberInput, 0);
        });
        schoolName.addEventListener('change', updateHiroconFields);

        purposeRadios.forEach(function (radio) {
            radio.addEventListener('change', updatePurposeView);
        });

        qtyInputs.forEach(function (input) {
            input.addEventListener('input', updateTotal);
        });

        goConfirmButton.addEventListener('click', function () {
            if (!validateStep2()) {
                return;
            }

            buildConfirmOutput();
            showStep(3);
        });

        form.addEventListener('submit', async function (event) {
            if (!form.checkValidity()) {
                return;
            }

            event.preventDefault();
            form.classList.add('is-submitting');
            setSubmitState(null, 'フォームを送信しています。<br>少々お待ちください。');

            form.querySelectorAll('button').forEach(function (button) {
                button.disabled = true;
            });

            const formData = new FormData(form);
            formData.append('certificate_form_submit', '1');

            try {
                const response = await fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const responseText = await response.text();
                const responseDocument = new DOMParser().parseFromString(responseText, 'text/html');
                const serverMessage = responseDocument.querySelector('.certificate-submit-message');
                const isSuccess = serverMessage && serverMessage.classList.contains('success');

                if (isSuccess) {
                    setSubmitState('success', '送信が完了しました。');
                    resetAppForm();

                    window.setTimeout(function () {
                        form.classList.remove('is-submitting');
                        submitOverlay.classList.remove('is-success', 'is-error');
                        submitPanel.classList.remove('success', 'error');

                        form.querySelectorAll('button').forEach(function (button) {
                            button.disabled = false;
                        });
                    }, 1700);
                } else {
                    setSubmitState('error', serverMessage ? escapeHtml(serverMessage.textContent) : '送信に失敗しました。時間を置いてもう一度お試しください。');

                    form.querySelectorAll('button').forEach(function (button) {
                        button.disabled = false;
                    });

                    unlockSubmitAfterError();
                }
            } catch (error) {
                setSubmitState('error', '送信に失敗しました。通信状況を確認してもう一度お試しください。');

                form.querySelectorAll('button').forEach(function (button) {
                    button.disabled = false;
                });

                unlockSubmitAfterError();
            }
        });

        updateStudentNumberCounter();
        updatePhoneNumberInput();
        updateHiroconFields();
        updatePurposeView();
        updateTotal();
        updateProgress();
    });
    </script>

    <?php
    return ob_get_clean();
});

if (!function_exists('certificate_sanitize_post_data')) {
    function certificate_sanitize_post_data($data) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $clean_key = sanitize_text_field(wp_unslash($key));

            if (is_array($value)) {
                $sanitized[$clean_key] = array_map(function ($item) {
                    return sanitize_textarea_field(wp_unslash($item));
                }, $value);
            } else {
                $sanitized[$clean_key] = sanitize_textarea_field(wp_unslash($value));
            }
        }

        return $sanitized;
    }
}
