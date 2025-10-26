/**
 * 围攻棋游戏管理后台JavaScript
 *
 * @package SiegeBoardGame
 */

(function($) {
    'use strict';

    // 管理后台主要功能
    var SiegeGameAdmin = {
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initTooltips();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            // 设置页面事件
            this.bindSettingsEvents();
            
            // 记录页面事件
            this.bindRecordsEvents();
            
            // 通用事件
            this.bindCommonEvents();
        },

        /**
         * 设置页面事件
         */
        bindSettingsEvents: function() {
            // 保存设置
            $('#siege-game-settings-form').on('submit', function(e) {
                e.preventDefault();
                SiegeGameAdmin.saveSettings($(this));
            });

            // 重置设置
            $('.reset-settings').on('click', function(e) {
                e.preventDefault();
                if (confirm(siegeGameAdmin.confirmReset)) {
                    SiegeGameAdmin.resetSettings();
                }
            });

            // 测试AI设置
            $('.test-ai').on('click', function(e) {
                e.preventDefault();
                SiegeGameAdmin.testAI();
            });

            // 颜色选择器
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    SiegeGameAdmin.updatePreview();
                }
            });

            // 实时预览
            $('#game-theme, #board-style, #piece-style').on('change', function() {
                SiegeGameAdmin.updatePreview();
            });
        },

        /**
         * 记录页面事件
         */
        bindRecordsEvents: function() {
            // 批量操作
            $('.tablenav .button.action').on('click', function(e) {
                var action = $(this).siblings('select[name="action"]').val();
                if (!action) {
                    e.preventDefault();
                    alert(siegeGameAdmin.selectAction);
                    return false;
                }
                
                var checkedItems = $('input[name="game_ids[]"]:checked');
                if (checkedItems.length === 0) {
                    e.preventDefault();
                    alert(siegeGameAdmin.selectItems);
                    return false;
                }
            });

            // 全选/取消全选
            $('#cb-select-all').on('change', function() {
                $('input[name="game_ids[]"]').prop('checked', this.checked);
            });

            // 查看游戏详情
            $('.view-game').on('click', function(e) {
                e.preventDefault();
                var gameId = $(this).data('game-id');
                SiegeGameAdmin.showGameDetails(gameId);
            });

            // 删除游戏
            $('.delete-game').on('click', function(e) {
                e.preventDefault();
                if (confirm(siegeGameAdmin.confirmDelete)) {
                    var gameId = $(this).data('game-id');
                    SiegeGameAdmin.deleteGame(gameId, $(this).closest('tr'));
                }
            });

            // 导出数据
            $('.export-games').on('click', function(e) {
                e.preventDefault();
                SiegeGameAdmin.exportGames();
            });
        },

        /**
         * 通用事件
         */
        bindCommonEvents: function() {
            // 清除缓存
            $('.clear-cache').on('click', function(e) {
                e.preventDefault();
                if (confirm(siegeGameAdmin.confirmClearCache)) {
                    SiegeGameAdmin.clearCache();
                }
            });

            // 刷新统计
            $('.refresh-stats').on('click', function(e) {
                e.preventDefault();
                SiegeGameAdmin.refreshStats();
            });

            // 模态框关闭
            $(document).on('click', '.siege-modal-close, .siege-modal', function(e) {
                if (e.target === this) {
                    $('.siege-modal').fadeOut(function() {
                        $(this).remove();
                    });
                }
            });

            // ESC键关闭模态框
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    $('.siege-modal').fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        },

        /**
         * 保存设置
         */
        saveSettings: function($form) {
            var formData = $form.serialize();
            formData += '&action=siege_game_action&game_action=save_settings&nonce=' + siegeGameAdmin.nonce;

            $.post(ajaxurl, formData, function(response) {
                if (response.success) {
                    SiegeGameAdmin.showNotice(siegeGameAdmin.settingsSaved, 'success');
                } else {
                    SiegeGameAdmin.showNotice(response.data || siegeGameAdmin.settingsError, 'error');
                }
            }).fail(function() {
                SiegeGameAdmin.showNotice(siegeGameAdmin.ajaxError, 'error');
            });
        },

        /**
         * 重置设置
         */
        resetSettings: function() {
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'reset_settings',
                nonce: siegeGameAdmin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    SiegeGameAdmin.showNotice(response.data || siegeGameAdmin.resetError, 'error');
                }
            });
        },

        /**
         * 测试AI
         */
        testAI: function() {
            var difficulty = $('#ai-difficulty').val();
            
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'test_ai',
                difficulty: difficulty,
                nonce: siegeGameAdmin.nonce
            }, function(response) {
                if (response.success) {
                    SiegeGameAdmin.showNotice(siegeGameAdmin.aiTestSuccess, 'success');
                } else {
                    SiegeGameAdmin.showNotice(response.data || siegeGameAdmin.aiTestError, 'error');
                }
            });
        },

        /**
         * 更新预览
         */
        updatePreview: function() {
            var theme = $('#game-theme').val();
            var boardStyle = $('#board-style').val();
            var pieceStyle = $('#piece-style').val();
            
            // 更新预览区域
            $('.game-preview').removeClass().addClass('game-preview theme-' + theme + ' board-' + boardStyle + ' pieces-' + pieceStyle);
        },

        /**
         * 显示游戏详情
         */
        showGameDetails: function(gameId) {
            var modal = $('<div class="siege-modal"><div class="siege-modal-content"><span class="siege-modal-close">&times;</span><div class="siege-modal-body"><div class="loading">' + siegeGameAdmin.loading + '</div></div></div></div>');
            $('body').append(modal);
            modal.fadeIn();

            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'get_game_details',
                game_id: gameId,
                nonce: siegeGameAdmin.nonce
            }, function(response) {
                if (response.success) {
                    modal.find('.siege-modal-body').html(response.data.html);
                } else {
                    modal.find('.siege-modal-body').html('<p class="error">' + (response.data || siegeGameAdmin.loadError) + '</p>');
                }
            }).fail(function() {
                modal.find('.siege-modal-body').html('<p class="error">' + siegeGameAdmin.ajaxError + '</p>');
            });
        },

        /**
         * 删除游戏
         */
        deleteGame: function(gameId, $row) {
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'delete_game',
                game_id: gameId,
                nonce: siegeGameAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $row.remove();
                    });
                    SiegeGameAdmin.showNotice(siegeGameAdmin.deleteSuccess, 'success');
                } else {
                    SiegeGameAdmin.showNotice(response.data || siegeGameAdmin.deleteError, 'error');
                }
            }).fail(function() {
                SiegeGameAdmin.showNotice(siegeGameAdmin.ajaxError, 'error');
            });
        },

        /**
         * 导出游戏数据
         */
        exportGames: function() {
            var filters = {
                user: $('input[name="search_user"]').val(),
                status: $('select[name="search_status"]').val(),
                date_from: $('input[name="date_from"]').val(),
                date_to: $('input[name="date_to"]').val()
            };

            var url = ajaxurl + '?action=siege_game_action&game_action=export_games&nonce=' + siegeGameAdmin.nonce;
            for (var key in filters) {
                if (filters[key]) {
                    url += '&' + key + '=' + encodeURIComponent(filters[key]);
                }
            }

            window.open(url, '_blank');
        },

        /**
         * 清除缓存
         */
        clearCache: function() {
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'clear_cache',
                nonce: siegeGameAdmin.nonce
            }, function(response) {
                if (response.success) {
                    SiegeGameAdmin.showNotice(siegeGameAdmin.cacheCleared, 'success');
                } else {
                    SiegeGameAdmin.showNotice(response.data || siegeGameAdmin.cacheError, 'error');
                }
            });
        },

        /**
         * 刷新统计
         */
        refreshStats: function() {
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'refresh_stats',
                nonce: siegeGameAdmin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    SiegeGameAdmin.showNotice(response.data || siegeGameAdmin.refreshError, 'error');
                }
            });
        },

        /**
         * 显示通知
         */
        showNotice: function(message, type) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">忽略此通知。</span></button></div>');
            
            $('.wrap h1').after(notice);
            
            // 自动隐藏成功通知
            if (type === 'success') {
                setTimeout(function() {
                    notice.fadeOut();
                }, 3000);
            }

            // 绑定关闭按钮
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut();
            });
        },

        /**
         * 初始化图表
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                return;
            }

            // 游戏统计图表
            var statsCanvas = $('#games-stats-chart');
            if (statsCanvas.length) {
                this.createStatsChart(statsCanvas[0]);
            }

            // 用户活跃度图表
            var activityCanvas = $('#user-activity-chart');
            if (activityCanvas.length) {
                this.createActivityChart(activityCanvas[0]);
            }
        },

        /**
         * 创建统计图表
         */
        createStatsChart: function(canvas) {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: siegeGameAdmin.chartData.labels,
                    datasets: [{
                        label: siegeGameAdmin.gamesPerDay,
                        data: siegeGameAdmin.chartData.games,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * 创建活跃度图表
         */
        createActivityChart: function(canvas) {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [siegeGameAdmin.activeUsers, siegeGameAdmin.inactiveUsers],
                    datasets: [{
                        data: [siegeGameAdmin.chartData.activeUsers, siegeGameAdmin.chartData.inactiveUsers],
                        backgroundColor: ['#46b450', '#dc3232']
                    }]
                },
                options: {
                    responsive: true
                }
            });
        },

        /**
         * 初始化工具提示
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        }
    };

    // 文档就绪时初始化
    $(document).ready(function() {
        SiegeGameAdmin.init();
    });

    // 全局暴露
    window.SiegeGameAdmin = SiegeGameAdmin;

})(jQuery);