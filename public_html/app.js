window.onload = function() { // fixes stupid scrollbar jumping if it happens
    setTimeout(function() {
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }, 30);
};

function scrollToElement(event, learnmore) {
    event.preventDefault();
    const targetElement = document.getElementById(learnmore);
    if (targetElement) {
        targetElement.scrollIntoView({ behavior: 'smooth' });
        history.replaceState({}, document.title, window.location.href.split('#')[0]);
    }
}