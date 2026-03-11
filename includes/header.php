<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// correct path to config folder
include(__DIR__ . '/../config/db.php');

// ======================================
// PAGE PROTECTION
// ======================================

// Get current file name
$current_page = basename($_SERVER['PHP_SELF']);

// Pages allowed without login
$allowed_pages = ['login.php', 'register.php'];

// If not logged in and not allowed page
if (!isset($_SESSION['user_id']) && !in_array($current_page, $allowed_pages)) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DA Borrowing System</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<?php include('../includes/sidebar.php'); ?>
<div class="topbar">
    <span class="topbar-title">DA Borrowing System</span>
    <div class="topbar-right">
    <?php if(isset($_SESSION['full_name'])): ?>
        <div class="user-profile">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
            <div class="user-info">
                <div class="name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="role">Administrator</div>
            </div>
        </div>
        <button class="logout-btn" onclick="showLogoutModal()">Logout</button>
    <?php endif; ?>
    </div>
</div>

<!-- Logout Modal -->
<div id="logoutModal" class="modal">
    <div class="modal-content">
        <h2>Confirm Logout</h2>
        <p>Are you sure you want to logout?</p>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="hideLogoutModal()">Cancel</button>
            <form method="post" class="inline-form">
                <button type="submit" name="confirm_logout">Logout</button>
            </form>
        </div>
    </div>
</div>

<!-- Global Popup Modal -->
<div id="appPopupModal" class="modal">
    <div class="modal-content app-popup-content" role="dialog" aria-modal="true" aria-labelledby="appPopupTitle">
        <h2 id="appPopupTitle">Notice</h2>
        <p id="appPopupMessage"></p>

        <div id="appPopupInputWrap" class="app-popup-input-wrap hidden">
            <input type="text" id="appPopupInput" class="app-popup-input" autocomplete="off">
        </div>

        <div class="modal-actions">
            <button type="button" id="appPopupCancelBtn" class="btn-secondary">Cancel</button>
            <button type="button" id="appPopupOkBtn">OK</button>
        </div>
    </div>
</div>

<main class="main-content">

<?php
 // If user confirms logout
  if(isset($_POST['confirm_logout'])){
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<script>
function showLogoutModal() {
    document.getElementById('logoutModal').classList.add('is-open');
}

function hideLogoutModal() {
    document.getElementById('logoutModal').classList.remove('is-open');
}

const appPopupState = {
    mode: 'alert',
    resolver: null
};

function resolveAppPopup(value) {
    const modal = document.getElementById('appPopupModal');
    if (modal) {
        modal.classList.remove('is-open');
    }

    if (typeof appPopupState.resolver === 'function') {
        const done = appPopupState.resolver;
        appPopupState.resolver = null;
        done(value);
    }
}

function openAppPopup(options) {
    return new Promise((resolve) => {
        const modal = document.getElementById('appPopupModal');
        const title = document.getElementById('appPopupTitle');
        const message = document.getElementById('appPopupMessage');
        const inputWrap = document.getElementById('appPopupInputWrap');
        const input = document.getElementById('appPopupInput');
        const cancelBtn = document.getElementById('appPopupCancelBtn');
        const okBtn = document.getElementById('appPopupOkBtn');

        if (!modal || !title || !message || !inputWrap || !input || !cancelBtn || !okBtn) {
            resolve(false);
            return;
        }

        appPopupState.mode = options.mode || 'alert';
        appPopupState.resolver = resolve;

        title.textContent = options.title || (appPopupState.mode === 'confirm' ? 'Confirm' : 'Notice');
        message.textContent = options.message || '';
        okBtn.textContent = options.okLabel || (appPopupState.mode === 'prompt' ? 'Submit' : 'OK');
        cancelBtn.textContent = options.cancelLabel || 'Cancel';

        if (appPopupState.mode === 'alert') {
            cancelBtn.style.display = 'none';
            inputWrap.classList.add('hidden');
            input.value = '';
        } else if (appPopupState.mode === 'confirm') {
            cancelBtn.style.display = '';
            inputWrap.classList.add('hidden');
            input.value = '';
        } else {
            cancelBtn.style.display = '';
            inputWrap.classList.remove('hidden');
            input.value = options.defaultValue || '';
            input.placeholder = options.placeholder || '';
        }

        modal.classList.add('is-open');

        window.setTimeout(() => {
            if (appPopupState.mode === 'prompt') {
                input.focus();
                input.select();
            } else {
                okBtn.focus();
            }
        }, 20);
    });
}

function handleAppPopupConfirm() {
    if (appPopupState.mode === 'prompt') {
        const input = document.getElementById('appPopupInput');
        resolveAppPopup(input ? input.value : '');
        return;
    }

    if (appPopupState.mode === 'alert') {
        resolveAppPopup(true);
        return;
    }

    resolveAppPopup(true);
}

function handleAppPopupCancel() {
    if (appPopupState.mode === 'prompt') {
        resolveAppPopup(null);
        return;
    }
    resolveAppPopup(false);
}

window.showAppAlert = function(message, options = {}) {
    return openAppPopup({
        mode: 'alert',
        title: options.title || 'Notice',
        message: message,
        okLabel: options.okLabel || 'OK'
    }).then(() => true);
};

window.showAppConfirm = function(message, options = {}) {
    return openAppPopup({
        mode: 'confirm',
        title: options.title || 'Confirm',
        message: message,
        okLabel: options.okLabel || 'Confirm',
        cancelLabel: options.cancelLabel || 'Cancel'
    });
};

window.showAppPrompt = function(message, options = {}) {
    return openAppPopup({
        mode: 'prompt',
        title: options.title || 'Input Required',
        message: message,
        okLabel: options.okLabel || 'Submit',
        cancelLabel: options.cancelLabel || 'Cancel',
        defaultValue: options.defaultValue || '',
        placeholder: options.placeholder || ''
    });
};

document.addEventListener('DOMContentLoaded', function() {
    const popupModal = document.getElementById('appPopupModal');
    const popupCancelBtn = document.getElementById('appPopupCancelBtn');
    const popupOkBtn = document.getElementById('appPopupOkBtn');
    const popupInput = document.getElementById('appPopupInput');

    if (popupCancelBtn) {
        popupCancelBtn.addEventListener('click', handleAppPopupCancel);
    }

    if (popupOkBtn) {
        popupOkBtn.addEventListener('click', handleAppPopupConfirm);
    }

    if (popupInput) {
        popupInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleAppPopupConfirm();
            }
        });
    }

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (popupModal && popupModal.classList.contains('is-open')) {
            handleAppPopupCancel();
            return;
        }

        const logoutModal = document.getElementById('logoutModal');
        if (logoutModal && logoutModal.classList.contains('is-open')) {
            hideLogoutModal();
        }
    });

    document.addEventListener('click', function(event) {
        const logoutModal = document.getElementById('logoutModal');
        if (logoutModal && event.target === logoutModal) {
            hideLogoutModal();
            return;
        }

        if (popupModal && event.target === popupModal) {
            handleAppPopupCancel();
        }
    });
});
</script>
