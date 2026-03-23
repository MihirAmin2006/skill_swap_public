<?php
header('Content-Type: application/javascript');
?>
let isEditMode = false;
let originalPhoto = '<?php echo htmlspecialchars($user['profile_photo']); ?>';
let newPhotoFile = null;

function toggleDarkMode() {
document.documentElement.classList.toggle('dark');
localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
}

// Load dark mode preference
if (localStorage.getItem('darkMode') === 'true' || localStorage.getItem('darkMode') === null) {
document.documentElement.classList.add('dark');
}

function toggleEditMode() {
isEditMode = !isEditMode;

if (isEditMode) {
// Switch to edit mode
document.getElementById('viewMode').classList.add('hidden');
document.getElementById('editMode').classList.remove('hidden');
document.getElementById('bioViewMode').classList.add('hidden');
document.getElementById('bioEditMode').classList.remove('hidden');
document.getElementById('skillsOfferView').classList.add('hidden');
document.getElementById('skillsOfferEdit').classList.remove('hidden');
document.getElementById('skillsLearnView').classList.add('hidden');
document.getElementById('skillsLearnEdit').classList.remove('hidden');
document.getElementById('editActions').classList.remove('hidden');
document.getElementById('changePhotoBtn').classList.remove('hidden');
document.getElementById('editToggleBtn').textContent = 'Cancel';
//document.getElementById('editToggleBtn').classList.remove('gradient-btn', 'shadow-btn');
//document.getElementById('editToggleBtn').classList.add('border', 'border-royal-basic/13', 'dark:border-royal-violet/18', 'text-royal-basic', 'dark:text-royal-violet', 'bg-transparent');
} else {
cancelEdit();
}
}

function cancelEdit() {
// Switch back to view mode
isEditMode = false;
document.getElementById('viewMode').classList.remove('hidden');
document.getElementById('editMode').classList.add('hidden');
document.getElementById('bioViewMode').classList.remove('hidden');
document.getElementById('bioEditMode').classList.add('hidden');
document.getElementById('skillsOfferView').classList.remove('hidden');
document.getElementById('skillsOfferEdit').classList.add('hidden');
document.getElementById('skillsLearnView').classList.remove('hidden');
document.getElementById('skillsLearnEdit').classList.add('hidden');
document.getElementById('editActions').classList.add('hidden');
document.getElementById('changePhotoBtn').classList.add('hidden');
document.getElementById('editToggleBtn').textContent = 'Edit Profile';
document.getElementById('editToggleBtn').classList.add('gradient-btn', 'shadow-btn');
document.getElementById('editToggleBtn').classList.remove('border', 'border-royal-basic/13', 'dark:border-royal-violet/18', 'text-royal-basic', 'dark:text-royal-violet', 'bg-transparent');

// Reset photo preview
document.getElementById('profilePhotoDisplay').src = originalPhoto;
newPhotoFile = null;
location.reload();
}

function previewPhoto(event) {
const file = event.target.files[0];
if (file && file.type.startsWith('image/')) {
const reader = new FileReader();
reader.onload = function(e) {
document.getElementById('profilePhotoDisplay').src = e.target.result;
newPhotoFile = file;
}
reader.readAsDataURL(file);
}
}

function saveProfile() {
    // Verify elements exist
    const nameEl = document.getElementById('editName');
    const usernameEl = document.getElementById('editUsername');
    const phoneEl = document.getElementById('editPhone');
    const bioEl = document.getElementById('editBio');
    
    if (!nameEl || !usernameEl) {
        alert('Form elements not found - check HTML IDs');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('name', nameEl.value.trim());
    formData.append('username', usernameEl.value.trim());
    formData.append('phone', phoneEl.value.trim());
    formData.append('bio', bioEl.value || '');
    
    const skillsOfferEl = document.getElementById('editSkillsOffer');
    const skillsLearnEl = document.getElementById('editSkillsLearn');
    formData.append('skills_offer', skillsOfferEl ? skillsOfferEl.value.trim() : '');
    formData.append('skills_learn', skillsLearnEl ? skillsLearnEl.value.trim() : '');

    if (newPhotoFile) {
        formData.append('profile_photo', newPhotoFile);
    }

    // DEBUG: Log what we're sending
    console.log('Form values:', {
        name: nameEl.value,
        username: usernameEl.value,
        phone: phoneEl.value,
        bio: bioEl.value,
        hasPhoto: !!newPhotoFile
    });
    console.log('FormData entries:', Array.from(formData.entries()));

    fetch(window.location.pathname, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'  // Include session cookies
    })
    .then(response => {
        console.log('Status:', response.status);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        const data = JSON.parse(text);
        if (data.success) {
            alert('✅ Profile updated!');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('❌ ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error details:', error);
        alert('Network error: ' + error.message);
    });
}