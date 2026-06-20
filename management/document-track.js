document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('.search-bar input');
    const navItems = document.querySelectorAll('.sidebar nav li');
    const shareBtn = document.querySelector('#shareBtn');

    // Modal elements
    const modal = document.getElementById('uploadModal');
    const closeModalBtn = document.querySelector('.close-modal');

    // Sections
    const tableContainer = document.querySelector('.table-container');
    const chatSection = document.getElementById('chatSection');
    const dashboardLink = document.getElementById('navDashboard');
    const chatsLink = document.getElementById('navChats');

    // Helper to get dynamic rows
    const getTableRows = () => document.querySelectorAll('#document-list tr');

    // Open Modal
    if (shareBtn) {
        shareBtn.addEventListener('click', () => {
            if (modal) modal.classList.add('active');
        });
    }

    // Close Modal
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            if (modal) modal.classList.remove('active');
        });
    }

    // Close on click outside
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    }

    // Toggle logic for SPA
    if (dashboardLink) {
        dashboardLink.addEventListener('click', (e) => {
            e.preventDefault();
            tableContainer.style.display = 'block';
            chatSection.style.display = 'none';

            navItems.forEach(nav => nav.classList.remove('active'));
            dashboardLink.classList.add('active');
            if (shareBtn) shareBtn.style.display = 'inline-block';
            if (searchInput) searchInput.parentElement.style.display = 'flex';
        });
    }

    if (chatsLink) {
        chatsLink.addEventListener('click', (e) => {
            e.preventDefault();
            tableContainer.style.display = 'none';
            chatSection.style.display = 'flex';

            navItems.forEach(nav => nav.classList.remove('active'));
            chatsLink.classList.add('active');
            if (shareBtn) shareBtn.style.display = 'none';
            if (searchInput) searchInput.parentElement.style.display = 'none';
        });
    }

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    // Category filtering
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const link = item.querySelector('a');
            if (item === dashboardLink || item === chatsLink) return; // Handled above
            if (link && link.getAttribute('href') !== '#') return; // Normal links

            e.preventDefault();

            // Update active class
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            // Force switch back to dashboard view if they click a category
            tableContainer.style.display = 'block';
            chatSection.style.display = 'none';
            if (shareBtn) shareBtn.style.display = 'inline-block';
            if (searchInput) searchInput.parentElement.style.display = 'flex';

            applyFilters();
        });
    });

    function applyFilters() {
        if (!searchInput) return;
        const searchTerm = searchInput.value.toLowerCase();

        // Find which tab/category is active
        let activeCategory = 'all';
        const activeNav = document.querySelector('.sidebar nav li.active');
        const activeTab = document.querySelector('.department-tabs .tab-btn.active');

        if (activeTab) {
            activeCategory = activeTab.textContent.trim().toLowerCase();
            // We removed the mapping to "Faculty Only", it's just "faculty" now
        } else if (activeNav) {
            activeCategory = activeNav.textContent.trim().toLowerCase();
            if (activeCategory.includes('all')) activeCategory = 'all';
        }

        const rows = getTableRows();
        let visibleCount = 0;

        rows.forEach(row => {
            // Check search text
            const textContent = row.textContent.toLowerCase();
            let matchesSearch = textContent.includes(searchTerm);

            // Check category matching
            const docCategory = (row.dataset.category || '').trim().toLowerCase();
            const uploaderBadge = row.querySelector('.badge');
            const uploaderDept = uploaderBadge ? uploaderBadge.textContent.trim().toLowerCase() : '';

            let matchesCategory = false;

            if (activeCategory === 'all') {
                matchesCategory = true;
            } else if (activeCategory === 'private') {
                matchesCategory = docCategory === 'private';
            } else if (activeCategory === 'public') {
                matchesCategory = docCategory === 'public';
            } else {
                // Department tabs
                if (uploaderDept.includes(activeCategory) && docCategory !== 'private') {
                    matchesCategory = true;
                }
            }

            if (matchesSearch && matchesCategory) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Handle noDocsRow visibility
        const noDocsRow = document.getElementById('noDocsRow');
        if (noDocsRow) {
            noDocsRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }
    // department-tabs

});

// ==========================================
// CHAT FUNCTIONALITY
// ==========================================

let currentReceiverId = null;
let chatInterval = null;

window.selectUser = function (element, id, username, role, profilePic) {
    // Update UI Selection
    document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
    if (element) element.classList.add('active');

    currentReceiverId = id;
    document.getElementById('receiverId').value = id;
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('activeChat').style.display = 'flex';

    document.getElementById('activeChatName').textContent = username;
    document.getElementById('activeChatRole').textContent = role;

    let avatarHtml = '';
    if (profilePic) {
        avatarHtml = `<img src="${profilePic}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">`;
    } else {
        avatarHtml = username.charAt(0).toUpperCase();
    }
    document.getElementById('activeChatAvatar').innerHTML = avatarHtml;

    // Load messages immediately
    fetchMessages();

    // Set up polling to refresh messages
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 3000);

    // Focus input
    document.getElementById('messageInput').focus();
}

async function fetchMessages() {
    if (!currentReceiverId) return;

    try {
        const response = await fetch(`fetch_messages.php?receiver_id=${currentReceiverId}`);
        const data = await response.json();

        if (data.status === 'success') {
            const messagesContainer = document.getElementById('chatMessages');
            // Check if scrolled to bottom before updating
            const isScrolledToBottom = messagesContainer.scrollHeight - messagesContainer.clientHeight <= messagesContainer.scrollTop + 50;

            messagesContainer.innerHTML = '';

            data.messages.forEach(msg => {
                const msgClass = msg.is_sent ? 'sent' : 'received';

                const msgDiv = document.createElement('div');
                msgDiv.className = `message ${msgClass}`;
                msgDiv.innerHTML = `
                    <div class="bubble">${escapeHtml(msg.message_text)}</div>
                    <div class="message-time">${msg.time}</div>
                `;
                messagesContainer.appendChild(msgDiv);
            });

            // Auto-scroll to bottom if it was at the bottom
            if (isScrolledToBottom || data.messages.length > 0) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
}

window.sendMessage = async function () {
    const input = document.getElementById('messageInput');
    const messageText = input.value.trim();
    const receiverId = document.getElementById('receiverId').value;

    if (!messageText || !receiverId) return;

    // Clear input
    input.value = '';

    try {
        const response = await fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `receiver_id=${receiverId}&message=${encodeURIComponent(messageText)}`
        });

        const data = await response.json();
        if (data.status === 'success') {
            // Fetch instantly to show the new message
            fetchMessages();
        } else {
            alert('Failed to send message: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
}

window.handleKeyPress = function (event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
