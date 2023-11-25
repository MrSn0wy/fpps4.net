let lightTheme = false;

const lightThemeCookie = checkCookies("lightTheme", "false");
if (lightThemeCookie === "true") {
  lightTheme = true;
  document.body.classList.toggle("lightMode");
}
//- Checks cookies

function LightModeIconChange() {
  if (lightTheme === true) {
    document.getElementById("lightModeIcon").style.display = "flex";
    document.getElementById("darkModeIcon").style.display = "none";
  } else {
    document.getElementById("darkModeIcon").style.opacity = "1";
  }
}

// Cookies
function setCookie(name, value) {
    var date = new Date();
    date.setMonth(date.getMonth() + 3);
    var expires = "; expires=" + date.toUTCString();
    document.cookie = name + "=" + value + expires + "; path=/";
  }

function checkCookies(name, defaultValue) {
  return document.cookie.split("; ").find(c => c.startsWith(name + "="))?.split("=")[1] ?? defaultValue; // if cookie doesnt exist use the default value
}

function toggleLightMode() {
    lightTheme = !lightTheme;
    setCookie("lightTheme", lightTheme);
    document.body.classList.toggle("lightMode");
    var x = document.getElementById("darkModeIcon")
    var y = document.getElementById("lightModeIcon")
    if (y.style.display === "none" || y.style.display == '') {
      y.style.display = "flex";
      x.style.display = "none";
    } else { 
      x.style.opacity = "1";
      x.style.display = "flex";
      y.style.display = "none";
    };
  };
  
function toggleMenu() {
  // var x = document.getElementById("overlay");
  var y = document.getElementById("close-icon")
  var z = document.getElementById("menu-icon")
  console.log('pretend like you see a menu')
  if (y.style.display === "none" || y.style.display == '') {
    // x.style.display = "flex";
    y.style.display = "flex";
    z.style.display = "none";
    setTimeout(function() {
      // x.style.opacity = 1;
    }, 50);
  } else { 
    // x.style.opacity = 0;
    y.style.display = "none";
    z.style.display = "flex";
    setTimeout(function() {
      // x.style.display = "none";
    }, 300); 
  };
};

// update footer position
function updateFooter() {
  const viewportHeight = window.innerHeight;
  const bodyHeight = document.body.clientHeight;
  if (bodyHeight < viewportHeight) {
    document.getElementById('footer').style.position = 'fixed';
  } else {
    document.getElementById('footer').style = '';
  }
}

// Adjust screen size for mobile and 4k monitors for some reason
function adjustScreenSize() {
  var screenWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
  var referenceWidth = 2000;
  var referenceFontSize = 16;
  var phoneReferenceWidth = 550;

  if (screenWidth >= referenceWidth) { // 2000 ++
    var fontSize = (screenWidth / referenceWidth) * referenceFontSize;
    document.documentElement.style.width = "";
    document.documentElement.style.fontSize = fontSize + 'px';

  } else if (screenWidth >= phoneReferenceWidth) { // 550 - 2000
    document.documentElement.style.fontSize = referenceFontSize + 'px';
    document.documentElement.style.width = "";

  } else { // -- 550
    var fontSize = (screenWidth / phoneReferenceWidth) * referenceFontSize;
    document.documentElement.style.width = screenWidth + "px";
    var viewport = document.querySelector('meta[name="viewport"]');
    viewport.content = "initial-scale=1";
    document.documentElement.style.zoom = "1";
    document.documentElement.style.fontSize = fontSize + 'px';
  }
}