<?php
$pageTitle = '直播场次';
$currentPage = 'sessions';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">📺 直播场次管理</div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="font-size:16px; color:var(--text);">创建新场次</h3>
                <button class="btn btn-success" onclick="createSession()">🆕 创建新场次</button>
            </div>

            <div style="padding:15px; background:var(--info-light); border-radius:8px; margin-bottom:20px; border:1px solid rgba(96,165,250,0.2);">
                <strong>💡 说明：</strong>创建场次时会自动复制当前主库存作为直播库存快照，直播期间销售不影响主库存。
            </div>
        </div>

        <div class="card">
            <div class="card-title">历史场次</div>
            <table>
                <thead>
                    <tr>
                        <th>场次名称</th>
                        <th>状态</th>
                        <th>开始时间</th>
                        <th>结束时间</th>
                        <th>销售额</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="sessionList"></tbody>
            </table>
        </div>

        <div class="modal" id="createModal">
            <div class="modal-content"><!-- 创建场次 -->
                <div class="modal-header">
                    <h3 class="modal-title">创建新直播场次</h3>
                    <button class="modal-close" onclick="closeCreateModal()">&times;</button>
                </div>
                <form onsubmit="saveSession(event)">
                    <div class="form-group">
                        <label class="form-label">场次名称</label>
                        <input type="text" class="form-input" id="sessionName" required placeholder="例如: 4月30日晚场">
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-success" style="flex:1;">创建并开始直播</button>
                        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="broadcastModal">
            <div class="modal-content"><!-- 主播提示 -->
                <div class="modal-header">
                    <h3 class="modal-title">发送提示给主播</h3>
                    <button class="modal-close" onclick="closeBroadcastModal()">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <input type="hidden" id="broadcastSessionId">
                    <div class="form-group">
                        <label class="form-label">提示内容</label>
                        <textarea class="form-input" id="broadcastMessage" rows="6" placeholder="输入要显示给主播的提示信息..." style="font-size:16px; padding:12px; resize:vertical;"></textarea>
                    </div>
                    <div style="font-size:13px; color:var(--text-secondary); margin-top:8px;">
                        💡 提示：消息会在直播屏幕上显示5秒后自动消失
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-warning" style="flex:1;" onclick="sendBroadcast()">发送</button>
                    <button class="btn btn-secondary" onclick="closeBroadcastModal()">取消</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function loadSessions() {
        try {
            const res = await fetch('../api/list_sessions.php');
            const data = await res.json();
            const sessions = Array.isArray(data.data)
                ? data.data
                : (data.data && Array.isArray(data.data.sessions) ? data.data.sessions : []);
            renderSessions(sessions);
        } catch (err) {
            console.error(err);
        }
    }

    function renderSessions(sessions) {
        const tbody = document.getElementById('sessionList');
        const statusNames = { pending: '待开始', active: '进行中', ended: '已结束' };
        const statusClasses = { pending: 'badge-warning', active: 'badge-success', ended: 'badge-info' };

        if (!sessions.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无场次记录</td></tr>';
            return;
        }

        tbody.innerHTML = sessions.map(s => `
            <tr>
                <td><strong>${s.session_name}</strong></td>
                <td><span class="badge ${statusClasses[s.status]}">${statusNames[s.status]}</span></td>
                <td>${s.started_at || '-'}</td>
                <td>${s.ended_at || '-'}</td>
                <td class="text-success">${s.total_sales ? '¥' + parseFloat(s.total_sales).toFixed(2) : '-'}</td>
                <td>
                    ${s.status === 'active' ?
                        `<button class="btn btn-sm btn-danger" onclick="endSession(${s.id})">结束</button>
                         <button class="btn btn-sm btn-warning" onclick="openBroadcastModal(${s.id})">发送提示</button>
                         <a href="../live.php?session_id=${s.id}" target="_blank" class="btn btn-sm btn-success">进入直播</a>` : ''}
                    ${s.status === 'ended' ?
                        `<button class="btn btn-sm btn-secondary" onclick="viewReport(${s.id})">查看报表</button>` : ''}
                    <button class="btn btn-sm btn-outline" onclick="deleteSession(${s.id})" style="color:var(--danger); border-color:var(--danger);">删除</button>
                </td>
            </tr>
        `).join('');
    }

    function createSession() {
        const now = new Date();
        const month = now.getMonth() + 1;
        const day = now.getDate();
        const hour = now.getHours();
        const period = hour < 12 ? '上午' : hour < 18 ? '下午' : '晚间';
        document.getElementById('sessionName').value = `${month}月${day}日${period}场`;
        document.getElementById('createModal').classList.add('show');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('show');
    }

    async function saveSession(e) {
        e.preventDefault();

        const sessionName = document.getElementById('sessionName').value;
        if (!sessionName.trim()) {
            alert('请输入场次名称');
            return;
        }

        try {
            const res = await fetch('../api/create_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_name: sessionName })
            });
            const result = await res.json();
            if (result.success) {
                const items = result.data.inventory_items || 0;
                const sessionId = result.data.session_id || (result.data.session && result.data.session.id);
                alert(`✅ ${result.message}！共 ${items} 个库存项已复制\n\n即将打开直播界面...`);
                closeCreateModal();
                loadSessions();
                const liveUrl = sessionId ? `../live.php?session_id=${sessionId}` : '../live.php';
                window.open(liveUrl, '_blank');
            } else {
                alert(result.error || '创建失败');
            }
        } catch (err) {
            alert('创建失败');
        }
    }

    async function endSession(sessionId) {
        if (!confirm('确定要结束这场直播吗？')) {
            return;
        }

        try {
            const res = await fetch('../api/end_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId })
            });
            const result = await res.json();
            if (result.success) {
                alert('直播已结束');
                loadSessions();
            } else {
                alert(result.error || '操作失败');
            }
        } catch (err) {
            alert('操作失败');
        }
    }

    function viewReport(sessionId) {
        window.location.href = `sales.php?session_id=${sessionId}`;
    }

    async function deleteSession(sessionId) {
        if (!confirm('确定要删除这个直播场次吗？\n\n注意：进行中的场次无法删除')) {
            return;
        }

        try {
            const res = await fetch('../api/delete_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId })
            });
            const result = await res.json();
            if (result.success) {
                alert('删除成功');
                loadSessions();
            } else {
                alert(result.error || '删除失败');
            }
        } catch (err) {
            alert('删除失败');
        }
    }

    function openBroadcastModal(sessionId) {
        document.getElementById('broadcastSessionId').value = sessionId;
        document.getElementById('broadcastMessage').value = '';
        document.getElementById('broadcastModal').classList.add('show');
        setTimeout(() => {
            document.getElementById('broadcastMessage').focus();
        }, 100);
    }

    function closeBroadcastModal() {
        document.getElementById('broadcastModal').classList.remove('show');
    }

    document.getElementById('broadcastMessage').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendBroadcast();
        }
    });

    async function sendBroadcast() {
        const sessionId = document.getElementById('broadcastSessionId').value;
        const message = document.getElementById('broadcastMessage').value.trim();

        if (!message) {
            alert('请输入提示内容');
            return;
        }

        try {
            const res = await fetch('../api/send_broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: parseInt(sessionId), message: message })
            });

            const result = await res.json();
            if (result.success) {
                closeBroadcastModal();
            } else {
                alert(result.error || '发送失败');
            }
        } catch (err) {
            alert('发送失败');
        }
    }

    loadSessions();
    </script>
</body>
</html>