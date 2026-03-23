setTimeout(() => {
    const errorBox = document.querySelector('.error_msg');
    const successBox = document.querySelector('.success_msg');
    if(successBox) successBox.style.display = 'none';
    if(errorBox) errorBox.style.display = 'none';
}, 5000);