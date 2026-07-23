<?php
add_shortcode('company_description_form', function () {
    $submit_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_description_submit'])) {
        $gas_url = 'https://script.google.com/macros/s/AKfycbw-z6zP0K774d9jFyxE5noYQcelOfNJxCGxe71C312EFEL-_49yl5yzfqt7KjKFdxxs6A/exec';

        $payload = company_description_sanitize_post_data($_POST);
        unset($payload['company_description_submit']);
        $payload['レポート種別'] = '企業訪問・企業説明会報告書';

        $request_args = [
            'headers' => [
                'User-Agent' => 'WordPress Company Description Form',
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
                        'User-Agent' => 'WordPress Company Description Form',
                    ],
                    'timeout' => 30,
                    'redirection' => 5,
                ]);
            }
        }

        if (is_wp_error($response)) {
            $submit_message = '<div class="company-submit-message error">送信失敗: WordPress HTTP Error<br>' . esc_html($response->get_error_message()) . '</div>';
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code >= 200 && $code < 300) {
                $decoded_body = json_decode($body, true);

                if (is_array($decoded_body) && isset($decoded_body['ok']) && $decoded_body['ok'] === true) {
                    $submit_message = '<div class="company-submit-message success">送信が完了しました。</div>';
                } elseif (is_array($decoded_body) && isset($decoded_body['ok']) && $decoded_body['ok'] === false) {
                    $submit_message = '<div class="company-submit-message error">GAS処理失敗:<br>' . esc_html($body) . '</div>';
                } else {
                    $submit_message = '<div class="company-submit-message error">GAS処理失敗: JSON応答を確認できません。<br><pre style="white-space:pre-wrap;">' . esc_html(mb_substr($body, 0, 1500)) . '</pre></div>';
                }
            } else {
                $headers = wp_remote_retrieve_headers($response);
                $debug_url = $redirect_url ? $redirect_url : $gas_url;
                $submit_message = '<div class="company-submit-message error">送信失敗: HTTP ' . esc_html($code) . '<br><pre style="white-space:pre-wrap;">URL: ' . esc_html(mb_substr($debug_url, 0, 500)) . "\n\n" . esc_html(print_r($headers, true)) . '</pre><br>' . esc_html(mb_substr($body, 0, 1000)) . '</div>';
            }
        }
    }

    ob_start();
    ?>

    <style>
        .company-description-form {
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
        .company-description-form fieldset {
            margin: 24px 0;
            padding: 22px;
            border: 1px solid #dbe2ec;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.06);
        }
        .company-description-form legend {
            font-weight: bold;
            padding: 0 8px;
            color: #0f4c81;
        }
        .company-description-form label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 14px;
            font-weight: bold;
            color: #1f2937;
        }
        .company-description-form input,
        .company-description-form select,
        .company-description-form textarea {
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
        .company-description-form textarea {
            min-height: 150px;
            resize: vertical;
        }
        .company-description-form input:focus,
        .company-description-form select:focus,
        .company-description-form textarea:focus {
            outline: none;
            border-color: #1f78b4;
            box-shadow: 0 0 0 4px rgba(31, 120, 180, 0.14);
            background: #fbfdff;
        }
        .company-description-form .form-step,
        .company-description-form .hidden-section {
            display: none;
        }
        .company-description-form .form-step.active {
            display: block;
            animation: companyStepSlideIn 0.34s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .company-description-form .form-step.slide-back.active {
            animation: companyStepSlideBack 0.34s cubic-bezier(0.22, 1, 0.36, 1);
        }
        @keyframes companyStepSlideIn {
            from { opacity: 0; transform: translateX(46px) scale(0.985); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        @keyframes companyStepSlideBack {
            from { opacity: 0; transform: translateX(-46px) scale(0.985); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        .company-description-form .step-nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
            align-items: center;
        }
        .company-description-form button {
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
        .company-description-form button:hover {
            background: #0b5c8d;
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15, 110, 168, 0.28);
        }
        .company-description-form button:disabled {
            cursor: wait;
            opacity: 0.72;
            transform: none;
        }
        .company-description-form .secondary-button {
            background: #eef3f8;
            color: #164b73;
            border: 1px solid #bed0df;
            box-shadow: 0 5px 14px rgba(30, 41, 59, 0.08);
        }
        .company-description-form .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }
        .company-description-form .radio-group label {
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
        .company-description-form .radio-group input {
            width: auto;
            margin-top: 0;
        }
        .company-description-form .progress-shell {
            margin-bottom: 24px;
            padding: 14px 16px;
            border: 1px solid #dbe2ec;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(30, 41, 59, 0.06);
        }
        .company-description-form .progress-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            color: #334155;
            font-weight: bold;
            font-size: 14px;
        }
        .company-description-form .progress-track {
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e7edf4;
        }
        .company-description-form .progress-fill {
            display: block;
            height: 100%;
            width: 25%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f6ea8, #25a36f);
            transition: width 0.34s ease;
        }
        .company-description-form .datetime-period-row,
        .company-description-form .meeting-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .company-description-form .form-subtitle {
            margin: 22px 0 10px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
            color: #164b73;
            font-size: 15px;
            font-weight: bold;
        }
        .company-description-form .confirm-list {
            margin-top: 16px;
            padding: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
        }
        .company-description-form .confirm-list dt {
            font-weight: bold;
            margin-top: 12px;
        }
        .company-description-form .confirm-list dd {
            margin-left: 0;
            white-space: pre-wrap;
        }
        .company-description-form .char-counter {
            margin-top: 5px;
            text-align: right;
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
            line-height: 1;
        }
        .company-description-form .char-counter.is-limit {
            color: #b91c1c;
        }
        .company-description-form .input-counter {
            margin-top: 5px;
            text-align: right;
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
            line-height: 1;
        }
        .company-description-form .input-counter.is-complete {
            color: #116b35;
        }
        .company-description-form .input-counter.is-error {
            color: #b91c1c;
        }
        .company-description-form .help-tip {
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
        .company-description-form .help-tip::after {
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
        .company-description-form .help-tip:hover::after,
        .company-description-form .help-tip:focus::after {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        .company-description-form.is-submitting .company-submit-overlay {
            display: flex;
        }
        .company-submit-overlay {
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
        .company-submit-panel {
            width: min(520px, 100%);
            padding: 40px;
            border-radius: 16px;
            background: #fff;
            text-align: center;
            box-shadow: 0 18px 45px rgba(30, 41, 59, 0.18);
            color: #172033;
            font-weight: bold;
            font-size: 18px;
            animation: companySubmitPanelIn 0.22s ease-out;
        }
        .company-submit-check {
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
            animation: companyCheckPop 0.34s ease-out;
        }
        .company-spinner {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border: 7px solid #dbe8f2;
            border-top-color: #0f6ea8;
            border-radius: 50%;
            animation: companySpin 0.8s linear infinite;
        }
        .company-submit-overlay.is-success .company-spinner,
        .company-submit-overlay.is-error .company-spinner {
            display: none;
        }
        .company-submit-overlay.is-success .company-submit-check {
            display: inline-flex;
        }
        .company-submit-message {
            max-width: 900px;
            margin: 16px auto;
            padding: 14px 18px;
            border-radius: 8px;
            font-weight: bold;
            animation: companyCardIn 0.3s ease-out;
        }
        .company-submit-message.success::before {
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
            animation: companyCheckPop 0.34s ease-out;
        }
        .company-submit-message.success {
            background: #e8f6ee;
            color: #116b35;
            border: 1px solid #9ad4b0;
        }
        .company-submit-message.error {
            background: #fdecec;
            color: #9b1c1c;
            border: 1px solid #f3aaaa;
        }
        .company-form-version {
            margin-top: 12px;
            text-align: right;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1;
        }
        @keyframes companySpin { to { transform: rotate(360deg); } }
        @keyframes companySubmitPanelIn {
            from { opacity: 0; transform: translateY(8px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes companyCardIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes companyCheckPop {
            from { transform: scale(0.55); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @media (max-width: 600px) {
            .company-description-form {
                padding: 16px;
            }
            .company-description-form .datetime-period-row,
            .company-description-form .meeting-row {
                grid-template-columns: 1fr;
            }
            .company-description-form .step-nav {
                flex-direction: column;
                align-items: stretch;
            }
            .company-description-form button {
                width: 100%;
            }
        }
    </style>

    <?php echo $submit_message; ?>

    <form class="company-description-form" method="post">
        <div class="progress-shell" aria-label="入力進捗">
            <div class="progress-meta">
                <span id="company_progress_step_text">STEP 1 / 4</span>
                <span id="company_progress_percent_text">25%</span>
            </div>
            <div class="progress-track" aria-hidden="true">
                <span class="progress-fill" id="company_progress_fill"></span>
            </div>
        </div>

        <section class="form-step active" data-step="1">
            <div class="step-indicator">STEP 1 / 4　基本情報</div>

            <fieldset>
                <legend>基本情報</legend>

                <label>学籍番号</label>
                <input type="text" id="company_student_number" name="学籍番号" inputmode="numeric" pattern="[0-9]{7}" maxlength="7" title="7桁の半角数字で入力してください" autocomplete="off" required>
                <div id="company_student_number_counter" class="input-counter">0 / 7</div>

                <label>名前</label>
                <input type="text" name="名前" placeholder="例：山田 太郎" required>

                <label>メールアドレス</label>
                <input type="email" name="メールアドレス" placeholder="例：student@example.com" required>

                <label>学校名</label>
                <select id="company_school_name" name="学校名" required>
                    <option value="">選択してください</option>
                    <option>広島コンピュータ専門学校</option>
                    <option>広島会計学院ビジネス専門学校</option>
                    <option>広島外語専門学校</option>
                    <option>広島美容専門学校</option>
                    <option>広島情報ビジネス専門学校</option>
                    <option>広島公務員専門学校</option>
                </select>

                <div id="company_hirocon_only_fields" class="hidden-section">
                    <label>コース名</label>
                    <select id="company_course_name_select" name="コース名">
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
                    <select id="company_teacher_name_select" name="担任">
                        <option value="">選択してください</option>
                        <option>山岡</option>
                        <option>森本</option>
                        <option>南</option>
                        <option>シゴレフ</option>
                        <option>竹田</option>
                        <option>新川</option>
                    </select>
                </div>

                <div id="company_other_school_fields">
                    <label>コース名</label>
                    <select id="company_hiroka_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>医療事務コース</option>
                        <option>フラワーデザイナーコース</option>
                        <option>経理財務コース</option>
                        <option>税理士マスターコース</option>
                        <option>税理士コース</option>
                    </select>
                    <select id="company_gaigo_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>総合英語コース</option>
                        <option>海外留学コース</option>
                        <option>国内大学編入コース</option>
                        <option>エアラインコース</option>
                        <option>ホテルコース</option>
                        <option>国際ビジネスコース</option>
                    </select>
                    <select id="company_biyo_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>トータルビューティ科 ヘアメイクコース</option>
                        <option>トータルビューティ科 メイクアップコース</option>
                        <option>トータルビューティ科 ネイルコース</option>
                        <option>トータルビューティ科 エステティックコース</option>
                    </select>
                    <select id="company_hjb_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>医療秘書コース</option>
                        <option>ブライダルコーディネーターコース</option>
                        <option>ペットビジネスコース</option>
                        <option>ペットケア＆トレーニングコース</option>
                        <option>情報ビジネスコース</option>
                        <option>販売ビジネスコース</option>
                    </select>
                    <select id="company_uhk_course_name_select" name="コース名" style="display: none;">
                        <option value="">選択してください</option>
                        <option>公務員科</option>
                        <option>公務員速成科</option>
                    </select>

                    <label>担任</label>
                    <input type="text" id="company_teacher_name_text" name="担任">
                </div>

                <label>性別</label>
                <div class="radio-group">
                    <label><input type="radio" name="性別" value="男" required>男</label>
                    <label><input type="radio" name="性別" value="女" required>女</label>
                </div>

                <label>提出日</label>
                <input type="date" name="提出日" required>
            </fieldset>

            <div class="step-nav">
                <button type="button" data-next-step="2">次へ</button>
            </div>
        </section>

        <section class="form-step" data-step="2">
            <div class="step-indicator">STEP 2 / 4　訪問・説明会情報</div>

            <fieldset>
                <legend>訪問・説明会情報</legend>

                <label>項目</label>
                <div class="radio-group">
                    <label><input type="radio" name="項目" value="企業訪問" required>企業訪問</label>
                    <label><input type="radio" name="項目" value="企業説明会" required>企業説明会</label>
                </div>

                <label>訪問先 <span class="help-tip" tabindex="0" role="img" aria-label="正式名称" data-tip="正式名称">?</span></label>
                <input type="text" name="訪問先" maxlength="56" data-counter-ready="false" required>

                <label>本社 <span class="help-tip" tabindex="0" role="img" aria-label="所在地" data-tip="所在地">?</span></label>
                <input type="text" name="本社" maxlength="56" data-counter-ready="false" required>

                <label>会場</label>
                <div class="radio-group">
                    <label><input type="radio" name="会場" value="本社" required>本社</label>
                    <label><input type="radio" name="会場" value="その他" required>その他</label>
                </div>

                <div id="company_venue_other_section" class="hidden-section">
                    <label>場所を入力してください</label>
                    <input type="text" name="その他会場" maxlength="80" data-counter-ready="false">
                </div>

                <div class="datetime-period-row">
                    <div>
                        <label>日時</label>
                        <input type="date" name="日時" required>
                    </div>
                    <div class="meeting-row">
                        <div>
                            <label>開始時間</label>
                            <input type="time" name="時間（開始）" required>
                        </div>
                        <div>
                            <label>終了時間</label>
                            <input type="time" name="時間（終了）" required>
                        </div>
                    </div>
                </div>

                <div class="form-subtitle">面談者</div>
                <div class="meeting-row">
                    <div>
                        <label>部署・役職</label>
                        <input type="text" name="面談者 部署・役職" maxlength="36">
                    </div>
                    <div>
                        <label>氏名</label>
                        <input type="text" name="面談者 氏名" maxlength="36">
                    </div>
                </div>
            </fieldset>

            <div class="step-nav">
                <button type="button" class="secondary-button" data-prev-step="1">戻る</button>
                <button type="button" data-next-step="3">次へ</button>
            </div>
        </section>

        <section class="form-step" data-step="3">
            <div class="step-indicator">STEP 3 / 4　受験意思の確認</div>

            <fieldset>
                <legend>受験意思の確認</legend>

                <label>受験意思</label>
                <div class="radio-group">
                    <label><input type="radio" name="受験意思" value="希望する" required>希望する</label>
                    <label><input type="radio" name="受験意思" value="検討中" required>検討中</label>
                    <label><input type="radio" name="受験意思" value="希望しない" required>希望しない</label>
                </div>

                <div id="company_no_exam_reason_section" class="hidden-section">
                    <label>希望しない理由</label>
                    <input type="text" name="希望しない理由" maxlength="30">
                </div>
            </fieldset>

            <fieldset>
                <legend>感想</legend>

                <label>感想 <span class="help-tip" tabindex="0" role="img" aria-label="当日内容・質問事項など記入" data-tip="当日内容・質問事項など記入">?</span></label>
                <textarea name="感想" maxlength="1300" data-counter-ready="false" required></textarea>
            </fieldset>

            <div class="step-nav">
                <button type="button" class="secondary-button" data-prev-step="2">戻る</button>
                <button type="button" id="company_go_confirm_step">確認へ進む</button>
            </div>
        </section>

        <section class="form-step" data-step="4">
            <div class="step-indicator">STEP 4 / 4　確認・送信</div>

            <fieldset>
                <legend>入力内容確認</legend>
                <div id="company_confirm_output" class="confirm-list"></div>
            </fieldset>

            <div class="step-nav">
                <button type="button" class="secondary-button" data-prev-step="3">戻る</button>
                <button type="submit" name="company_description_submit">送信</button>
            </div>
        </section>

        <div class="company-submit-overlay" role="status" aria-live="polite">
            <div class="company-submit-panel">
                <div class="company-spinner" aria-hidden="true"></div>
                <div class="company-submit-check" aria-hidden="true">✓</div>
                <div class="company-submit-status">フォームを送信しています。<br>少々お待ちください。</div>
            </div>
        </div>

        <div class="company-form-version">シゴレフ　エドワード　ver. 1.0</div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.company-description-form');
        if (!form) return;

        const steps = form.querySelectorAll('.form-step');
        const progressFill = document.getElementById('company_progress_fill');
        const progressStepText = document.getElementById('company_progress_step_text');
        const progressPercentText = document.getElementById('company_progress_percent_text');

        const studentNumberInput = document.getElementById('company_student_number');
        const studentNumberCounter = document.getElementById('company_student_number_counter');
        const schoolName = document.getElementById('company_school_name');
        const hiroconOnlyFields = document.getElementById('company_hirocon_only_fields');
        const otherSchoolFields = document.getElementById('company_other_school_fields');
        const courseNameSelect = document.getElementById('company_course_name_select');
        const hirokaCourseNameSelect = document.getElementById('company_hiroka_course_name_select');
        const gaigoCourseNameSelect = document.getElementById('company_gaigo_course_name_select');
        const biyoCourseNameSelect = document.getElementById('company_biyo_course_name_select');
        const hjbCourseNameSelect = document.getElementById('company_hjb_course_name_select');
        const uhkCourseNameSelect = document.getElementById('company_uhk_course_name_select');
        const teacherNameSelect = document.getElementById('company_teacher_name_select');
        const teacherNameText = document.getElementById('company_teacher_name_text');

        const venueRadios = form.querySelectorAll('input[name="会場"]');
        const venueOtherSection = document.getElementById('company_venue_other_section');
        const noExamReasonSection = document.getElementById('company_no_exam_reason_section');
        const examIntentRadios = form.querySelectorAll('input[name="受験意思"]');
        const goConfirmStepButton = document.getElementById('company_go_confirm_step');
        const confirmOutput = document.getElementById('company_confirm_output');

        const submitOverlay = form.querySelector('.company-submit-overlay');
        const submitPanel = form.querySelector('.company-submit-panel');
        const submitStatus = form.querySelector('.company-submit-status');

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

    updateAllCharacterCounters();
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

        function initCharacterCounters() {
    form.querySelectorAll('input[maxlength][data-counter-ready], textarea[maxlength][data-counter-ready]').forEach(function (field) {
        if (field.dataset.counterReady === 'true') {
            return;
        }

        const maxLength = Number(field.getAttribute('maxlength'));
        const counter = document.createElement('div');

        counter.className = 'char-counter';
        counter.setAttribute('aria-live', 'polite');
        field.insertAdjacentElement('afterend', counter);
        field.dataset.counterReady = 'true';

        field.addEventListener('input', function () {
            updateCharacterCounter(field, counter, maxLength);
        });

        updateCharacterCounter(field, counter, maxLength);
    });
}

function updateCharacterCounter(field, counter, maxLength) {
    const currentLength = field.value.length;

    counter.textContent = currentLength + ' / ' + maxLength;
    counter.classList.toggle('is-limit', currentLength >= maxLength);
}

function updateAllCharacterCounters() {
    form.querySelectorAll('input[maxlength][data-counter-ready], textarea[maxlength][data-counter-ready]').forEach(function (field) {
        const counter = field.nextElementSibling;

        if (counter && counter.classList.contains('char-counter')) {
            updateCharacterCounter(field, counter, Number(field.getAttribute('maxlength')));
        }
    });
}

        function updateVenueOther() {
            const selectedVenue = form.querySelector('input[name="会場"]:checked');
            let showsOtherVenue = false;

            if (selectedVenue) {
                showsOtherVenue = selectedVenue.value === 'その他';
            }
            const otherVenueInput = venueOtherSection.querySelector('input[name="その他会場"]');

            setSectionVisibility(venueOtherSection, Boolean(showsOtherVenue));
            otherVenueInput.required = Boolean(showsOtherVenue);
            otherVenueInput.disabled = !showsOtherVenue;
        }

        function updateNoExamReason() {
            const selectedIntent = form.querySelector('input[name="受験意思"]:checked');
            let showsReason = false;

            if (selectedIntent) {
                showsReason = selectedIntent.value === '希望しない';
            }
            const reasonInput = noExamReasonSection.querySelector('input[name="希望しない理由"]');

            setSectionVisibility(noExamReasonSection, Boolean(showsReason));
            reasonInput.required = Boolean(showsReason);
            reasonInput.disabled = !showsReason;
        }

        function validateCurrentStep(currentStep) {
            updateStudentNumberCounter();
            updateHiroconFields();
            updateVenueOther();
            updateNoExamReason();

            const activeStep = form.querySelector('.form-step[data-step="' + currentStep + '"]');
            const requiredFields = activeStep.querySelectorAll('[required]');

            for (const field of requiredFields) {
                if (field.disabled) {
                    continue;
                }

                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }

            return true;
        }

        function getFieldValue(name) {
            const fields = form.elements[name];
            let list;

            if (!fields) {
                return '';
            }

            list = fields.length === undefined || fields.tagName ? [fields] : fields;

            for (const field of list) {
                if (field.disabled) {
                    continue;
                }

                if (field.type === 'radio' || field.type === 'checkbox') {
                    if (field.checked) {
                        return field.value;
                    }
                    continue;
                }

                return field.value;
            }

            return '';
        }

        function buildConfirmOutput() {
            const rows = [
                ['学籍番号', getFieldValue('学籍番号')],
                ['名前', getFieldValue('名前')],
                ['メールアドレス', getFieldValue('メールアドレス')],
                ['学校名', getFieldValue('学校名')],
                ['コース名', getFieldValue('コース名')],
                ['担任', getFieldValue('担任')],
                ['性別', getFieldValue('性別')],
                ['提出日', getFieldValue('提出日')],
                ['項目', getFieldValue('項目')],
                ['訪問先', getFieldValue('訪問先')],
                ['本社', getFieldValue('本社')],
                ['会場', getFieldValue('会場')],
                ['その他会場', getFieldValue('その他会場')],
                ['日時', getFieldValue('日時')],
                ['時間（開始）', getFieldValue('時間（開始）')],
                ['時間（終了）', getFieldValue('時間（終了）')],
                ['面談者 部署・役職', getFieldValue('面談者 部署・役職')],
                ['面談者 氏名', getFieldValue('面談者 氏名')],
                ['受験意思', getFieldValue('受験意思')],
                ['希望しない理由', getFieldValue('希望しない理由')],
                ['感想', getFieldValue('感想')]
            ];

            let html = '<dl>';

            rows.forEach(function (row) {
                const value = String(row[1] || '').trim();

                if (!value) {
                    return;
                }

                html += '<dt>' + escapeHtml(row[0]) + '</dt>';
                html += '<dd>' + escapeHtml(value) + '</dd>';
            });

            html += '</dl>';
            confirmOutput.innerHTML = html;
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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

        schoolName.addEventListener('change', updateHiroconFields);

        venueRadios.forEach(function (radio) {
            radio.addEventListener('change', updateVenueOther);
        });

        examIntentRadios.forEach(function (radio) {
            radio.addEventListener('change', updateNoExamReason);
        });

        goConfirmStepButton.addEventListener('click', function () {
            if (!validateCurrentStep(3)) {
                return;
            }

            buildConfirmOutput();
            showStep(4);
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
            formData.append('company_description_submit', '1');

            try {
                const response = await fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const responseText = await response.text();
                const responseDocument = new DOMParser().parseFromString(responseText, 'text/html');
                const serverMessage = responseDocument.querySelector('.company-submit-message');
                let isSuccess = false;

                if (serverMessage) {
                    isSuccess = serverMessage.classList.contains('success');
                }

                if (isSuccess) {
                    setSubmitState('success', '送信が完了しました。');

                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1400);
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

        initCharacterCounters();
        updateStudentNumberCounter();
        updateAllCharacterCounters();
        updateHiroconFields();
        updateVenueOther();
        updateNoExamReason();
        updateProgress();
    });
    </script>

    <?php
    return ob_get_clean();
});

if (!function_exists('company_description_sanitize_post_data')) {
    function company_description_sanitize_post_data($data) {
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
