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

// Fetch html for footer and header
async function fetchHtml(path, target){
  return new Promise((resolve, reject) => {
    fetch(path).then(response => response.text()).then(data => {
      document.querySelector(target).innerHTML = data;
      resolve("done!")
    }).catch(() => {
      reject("error occurred while fetching html!")
    });
  })
}

function snow() {
  const body = document.querySelector("body");
  
  // const snowContainerHTML = `<div style="position: fixed; height: 100%; width: 100% " ></div>`;
  const snowContainer = document.createElement('div');
  snowContainer.style.position = "absolute";
  snowContainer.style.height = "100%";
  snowContainer.style.width = "100%";
  snowContainer.style.pointerEvents = "none";

  body.insertBefore(snowContainer, body.firstChild);
  // snowContainer.outerHTML = snowContainerHTML;
  
  function insert_snow(snow_size) {
    let random_size  = Math.floor(Math.random() * (snow_size - 2) + 2);
    
    const snowHTML = `
    <svg style="position: fixed;" width="${random_size}px" height="${random_size}px" opacity="40%" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="50" fill="white"/>
    </svg>`;

    const snow = document.createElement('svg');
    snowContainer.appendChild(snow);
    snow.outerHTML = snowHTML;
  }
  
  let snow_amount = (window.innerWidth / 13 + window.innerHeight / 13) / 2;
  let snow_size = (window.innerWidth / 230 + window.innerHeight / 230) / 2;
  
  console.log(snow_amount)
  console.log(snow_size)
  for (let step = 0; step <= snow_amount; step++) {
    insert_snow(snow_size);
  }
  
  for (let snow of  snowContainer.children) {
    let random_acceleration = Math.floor(Math.random() * (18 - 8) + 8);
    let random_pos_acceleration = (Math.random() * (2 - 0.3) + 0.3);
    let id = 0;
    let pos_y = 0;
    let pos_x = 0;
    
    clearInterval(id);
    id = setInterval(frame, random_acceleration);

    let start_x  = Math.floor(Math.random() * (window.innerWidth - (window.innerWidth / 20)));
    let start_y  = Math.floor(Math.random() * (window.innerHeight - (window.innerHeight / 20)));
    
    function frame() {
      // if it exceeds the browser's height, 
      if (start_y + pos_y >= window.innerHeight) {
        start_x  = Math.floor(Math.random() * (window.innerWidth - (window.innerWidth / 20)));
        random_pos_acceleration = (Math.random() * (2 - 0.3) + 0.3);
        pos_y = 0;
        pos_x = 0;
        start_y  = 0;
        
      } else if (start_x + (pos_x / 2) >= window.innerWidth){
        start_y  = Math.floor(Math.random() * (window.innerHeight - (window.innerHeight / 20)));
        random_pos_acceleration = (Math.random() * (2 - 0.3) + 0.3);
        pos_x = 0;
        pos_y = 0;
        start_x  = 0;
        
      } else {
        pos_y += 1 + random_pos_acceleration;
        pos_x += 0.7 + random_pos_acceleration;
        snow.style.top = start_y + pos_y + 'px';
        snow.style.left = start_x + pos_x + 'px';
      }
    }
  }
}

async function init() {
  // Header & Footer Load
  await fetchHtml("/_parts/navbar.html", "#header");
  await fetchHtml("/_parts/footer.html", "#footer");
  adjustScreenSize();
  snow();
  LightModeIconChange();
}