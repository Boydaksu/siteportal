/**
 * SitePortal — Analiz Yaşam Döngüsü ve İlerleme Takip İstemcisi
 *
 * Kurallar:
 * 1. Sahte yüzdelik (fake timer) YOK. İlerleme strictly sunucu durumundan türetilir.
 * 2. Erişilebilirlik (ARIA) standartlarına uygun durum güncellemeleri.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initAnalyzeForm();
        initProgressTracker();
    });

    /**
     * Ana Sayfa Form Gönderimi (AJAX & Progressive Enhancement)
     */
    function initAnalyzeForm() {
        var form = document.getElementById('analyze-form');
        if (!form) {
            return;
        }

        var urlInput = document.getElementById('url-input');
        var submitBtn = document.getElementById('submit-btn');
        var btnSpinner = document.getElementById('btn-spinner');
        var btnText = document.getElementById('btn-text');
        var errorClient = document.getElementById('form-error-client');
        var errorServer = document.getElementById('form-error-server');

        form.addEventListener('submit', function (e) {
            var url = urlInput ? urlInput.value.trim() : '';
            if (!url) {
                return; // HTML5 required validation handles empty input
            }

            // Basit protokol kontrolü (kullanıcı http/https yazmadıysa otomatik uyarabiliriz)
            if (!/^https?:\/\//i.test(url)) {
                e.preventDefault();
                showError('Lütfen geçerli bir http:// veya https:// adresi giriniz.');
                if (urlInput) {
                    urlInput.focus();
                }
                return;
            }

            // AJAX gönderimi
            e.preventDefault();
            setLoading(true);
            hideError();

            var formData = new FormData(form);
            var params = new URLSearchParams();
            formData.forEach(function (value, key) {
                params.append(key, value.toString());
            });

            fetch(form.action || '/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params.toString()
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && (result.status === 200 || result.status === 202)) {
                    var targetUrl = result.data.view_url || ('/analysis/' + result.data.analysis_id);
                    window.location.href = targetUrl;
                } else {
                    var msg = (result.data && (result.data.detail || result.data.title))
                        ? (result.data.detail || result.data.title)
                        : 'Analiz başlatılamadı. Lütfen tekrar deneyiniz.';
                    showError(msg);
                    setLoading(false);
                }
            })
            .catch(function () {
                // Ağ veya JSON hatası durumunda normal form submit fallback
                form.submit();
            });
        });

        function setLoading(isLoading) {
            if (submitBtn) {
                submitBtn.disabled = isLoading;
            }
            if (btnSpinner) {
                btnSpinner.style.display = isLoading ? 'inline-block' : 'none';
            }
            if (btnText && isLoading) {
                btnText.setAttribute('data-original-text', btnText.textContent);
                btnText.textContent = 'Analiz Başlatılıyor...';
            } else if (btnText && !isLoading && btnText.getAttribute('data-original-text')) {
                btnText.textContent = btnText.getAttribute('data-original-text');
            }
        }

        function showError(message) {
            if (errorServer) {
                errorServer.style.display = 'none';
            }
            if (errorClient) {
                errorClient.textContent = '⚠️ ' + message;
                errorClient.style.display = 'block';
            }
        }

        function hideError() {
            if (errorServer) {
                errorServer.style.display = 'none';
            }
            if (errorClient) {
                errorClient.style.display = 'none';
                errorClient.textContent = '';
            }
        }
    }

    /**
     * Analiz İlerleme Sayfası Gerçek Polling (Sahte Yüzdelik Yok)
     */
    function initProgressTracker() {
        var container = document.getElementById('analysis-progress-container');
        if (!container) {
            return;
        }

        var analysisId = container.getAttribute('data-analysis-id');
        var progressUrl = container.getAttribute('data-progress-url') || ('/api/v1/analyses/' + analysisId + '/progress');
        var currentStatus = container.getAttribute('data-status') || 'pending';

        var statusPill = document.getElementById('analysis-status-pill');
        var progressBarContainer = document.getElementById('progress-bar-container');
        var progressBarFill = document.getElementById('progress-bar-fill');
        var progressPercentLabel = document.getElementById('progress-percent-label');
        var liveBadge = document.getElementById('live-polling-badge');
        var errorBox = document.getElementById('progress-error-box');
        var errorText = document.getElementById('progress-error-text');
        var viewReportBtn = document.getElementById('view-report-btn');

        // Zaten sonlanmış ise polling başlatma
        if (currentStatus === 'completed' || currentStatus === 'failed') {
            return;
        }

        var pollIntervalMs = 1500;
        var timerId = null;

        function poll() {
            fetch(progressUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                applyServerState(data);

                if (data.status === 'completed' || data.status === 'failed') {
                    if (timerId) {
                        clearInterval(timerId);
                        timerId = null;
                    }
                }
            })
            .catch(function (err) {
                // Geçici ağ aksaklığında hemen hata verme, bir sonraki poll denesin
                console.warn('Progress poll issue:', err);
            });
        }

        function applyServerState(data) {
            var status = data.status || 'pending';
            var percent = typeof data.progress_percent === 'number' ? data.progress_percent : 0;

            // 1. İlerleme Çubuğu ve Yüzde Güncellemesi (Strictly DB kaynaklı)
            if (progressBarContainer) {
                progressBarContainer.setAttribute('aria-valuenow', percent.toString());
            }
            if (progressBarFill) {
                progressBarFill.style.width = percent + '%';
            }
            if (progressPercentLabel) {
                progressPercentLabel.textContent = percent + '%';
            }

            // 2. Durum Rozeti
            if (statusPill) {
                statusPill.textContent = status;
            }

            // 3. Aşamaların Güncellenmesi
            if (Array.isArray(data.stages)) {
                data.stages.forEach(function (stage) {
                    var item = document.getElementById('stage-item-' + stage.key);
                    if (!item) return;

                    var icon = item.querySelector('.stage-icon');
                    var badge = item.querySelector('.stage-state');

                    var st = stage.state;
                    var iconChar = '⚪';
                    var bg = 'transparent';
                    var color = 'var(--text-muted)';

                    if (st === 'completed') {
                        iconChar = '✅';
                        bg = 'rgba(34, 197, 94, 0.1)';
                        color = 'var(--success)';
                    } else if (st === 'in_progress') {
                        iconChar = '⏳';
                        bg = 'rgba(56, 189, 248, 0.1)';
                        color = 'var(--primary)';
                    } else if (st === 'failed') {
                        iconChar = '❌';
                        bg = 'rgba(239, 68, 68, 0.1)';
                        color = 'var(--danger)';
                    }

                    if (icon) icon.textContent = iconChar;
                    if (badge) {
                        badge.textContent = st;
                        badge.style.color = color;
                        badge.style.borderColor = color;
                    }
                    item.style.backgroundColor = bg;
                });
            }

            // 4. Tamamlanma veya Başarısızlık Eylemleri
            if (status === 'completed') {
                if (liveBadge) {
                    liveBadge.textContent = '✅ Analiz Tamamlandı';
                }
                if (viewReportBtn) {
                    viewReportBtn.style.display = 'inline-block';
                }
            } else if (status === 'failed') {
                if (liveBadge) {
                    liveBadge.textContent = '❌ Analiz Başarısız Oldu';
                }
                if (errorBox) {
                    errorBox.style.display = 'block';
                }
                if (errorText && data.error_message) {
                    errorText.textContent = 'Analiz sırasında bir sorun oluştu: ' + data.error_message;
                }
            }
        }

        timerId = setInterval(poll, pollIntervalMs);
    }
})();
