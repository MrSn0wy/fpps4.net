let imageLoading = true;
let avifSupport = false;
let sTimer;
let iTimer;
let pTimer;
let pbTimer;
let pfTimer;
let pmaxTimer;
let pminTimer;
let skeletonTimeout;
let tagFilter = [];
let oldestFilter = false;
let datesButton = false;
let lightTheme = false;
let pageNumber = 1;
let inputValue = 1;
let updatePageValue = false;
let noImagesLocked = false;
let maxValue;

var urlParams = new URLSearchParams(window.location.search);
var tagParam = urlParams.get('tag');
if (tagParam) {
  tagFilter.push(tagParam);
  tagElementForFilter = document.getElementById(tagParam + 'Bar');
  tagParentForFilter = tagElementForFilter.parentElement;
  tagParentForFilter.classList.toggle('selected');
}


function setCookie(name, value) {
  var date = new Date();
  date.setMonth(date.getMonth() + 1);
  var expires = "; expires=" + date.toUTCString();
  document.cookie = name + "=" + value + expires + "; path=/";
}

// Checks cookies
function checkCookies(name, defaultValue) {
  return document.cookie.split("; ").find(c => c.startsWith(name + "="))?.split("=")[1] ?? defaultValue; // if cookie doesnt exist use the default value
}

const imageLoadingCookie = checkCookies("imageLoading", "true");
if (imageLoadingCookie === "false") {
  imageLoading = false;
  document.getElementById("imageButton").classList.add('selected');
}

const dateCookie = checkCookies("datesButton", "false");
if (dateCookie === "true") {
  datesButton = true;

  document.getElementById("datesButton").classList.add('selected');
}

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

function adjustFontSize() {
  var screenWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
  var referenceWidth = 2000;
  var referenceFontSize = 16;
  var phoneReferenceWidth = 550;

  if (screenWidth >= referenceWidth) {
    // if its larger then 2000px
    var fontSize = (screenWidth / referenceWidth) * referenceFontSize;
    document.documentElement.style.fontSize = fontSize + 'px';
  } else if (screenWidth >= phoneReferenceWidth) {
    // if its higer then 550 but under 2000 then set it as default
    document.documentElement.style.fontSize = referenceFontSize + 'px';
  } else {
    // if the width is lower then 550
    var fontSize = (screenWidth / phoneReferenceWidth) * referenceFontSize;
    document.documentElement.style.fontSize = fontSize + 'px';
  }
}

// resize on load and when rezsizing the window
window.addEventListener('load', adjustFontSize);
window.addEventListener('resize', adjustFontSize);

// Check Avif Support
console.log(`Hey there! I've implemented an avif support check to spare browsers like Edge from having a stroke :D!`);
const avif = new Image();
avif.src = "data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAADybWV0YQAAAAAAAAAoaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAGxpYmF2aWYAAAAADnBpdG0AAAAAAAEAAAAeaWxvYwAAAABEAAABAAEAAAABAAABGgAAAB0AAAAoaWluZgAAAAAAAQAAABppbmZlAgAAAAABAABhdjAxQ29sb3IAAAAAamlwcnAAAABLaXBjbwAAABRpc3BlAAAAAAAAAAIAAAACAAAAEHBpeGkAAAAAAwgICAAAAAxhdjFDgQ0MAAAAABNjb2xybmNseAACAAIAAYAAAAAXaXBtYQAAAAAAAAABAAEEAQKDBAAAACVtZGF0EgAKCBgANogQEAwgMg8f8D///8WfhwB8+ErK42A=";
avif.onload = function() {
  console.log('AVIF IS SUPPORTED :D');
  avifSupport = true;
};
avif.onerror = function() {
  console.log('AVIF IS NOT SUPPORTED D:');
  avifSupport = false;
  noImagesLocked = true;
  window.alert("Hey! Your browser doesnt support avif, avif is an image format that has low file sizes while having high quality images. I will give you an N/A jpeg instead.");
  imageButton();
};

// Page Load
document.addEventListener('DOMContentLoaded', function() {
  fetch('https://fpps4.net/scripts/search.php?q=&stats&tag='+ tagFilter)
  .then(response => response.text())
  .then(data => {
    document.querySelector('#gameWrapper').innerHTML = data;
    applyMods();
    gameStats();
    filter();
  })
  .catch(console.error);
});

// Game Stats
function gameStats() {
  var labelData = [];
  var tempPercentage = 0;
  var tempTotal = 0;
  var labels = ['N/A', 'Nothing', 'Boots', 'Menus', 'Ingame', 'Playable'];
  console.log("\nCOMPATIBILITY STATS");

  labels.forEach(label => {
    var div = document.getElementById(label).getAttribute('data').split('+');
    var percentage = div[0];
    var total = div[1];
    labelData[label] = [percentage, total];
    console.log(label + ' = ' + percentage + '% ['+ total +']');
  });
  console.log("\n");

  for (var label in labelData) {
    var data = labelData[label];
    var percentage = data[0];
    var total = data[1];
    if (label === 'N/A' || label === 'Nothing') { //combine N/A & Nothing
      tempPercentage = (parseFloat(percentage) + parseFloat(tempPercentage));
      tempTotal = (parseFloat(total) + parseFloat(tempTotal));
      percentage = tempPercentage.toFixed(2);
      total = tempTotal.toFixed(0);
      element = document.getElementById('NothingBar');
      text = document.getElementById('NothingInfo')
    } else {
      element = document.getElementById(label + 'Bar');
      text = document.getElementById(label + 'Info')
    }
    element.style.width = percentage + '%';
    text.textContent = percentage + '% - ' + total;
  }
};

// Header Load
fetch('https://fpps4.net/parts/navbar.html')
.then(response => response.text())
.then(data => {
  document.querySelector('#header').innerHTML = data;
  LightModeIconChange();
})
.catch(console.error);

// Searching
document.querySelector('#search').addEventListener('input', function() {
  updatePageValue = true;
  pageNumber = 1;
  UpdateSearchResults()
});

function UpdateSearchResults() {
  clearTimeout(sTimer);

  const gameWrapper = document.querySelector('#gameWrapper');
  gameWrapper.querySelectorAll('.gameContainer').forEach(container => {
    const skeletonDiv = document.createElement('div');
    skeletonDiv.classList.add('gameContainer', 'skeletonAnimation');
    gameWrapper.replaceChild(skeletonDiv, container);
  });
    
  sTimer = setTimeout(() => {
    const searchQuery = document.querySelector('#search').value;
    fetch('https://fpps4.net/scripts/search.php?q=' + searchQuery + '&tag=' + tagFilter + '&page=' + pageNumber + '&oldest=' + oldestFilter)
      .then(response => response.text())
      .then(data => {
        document.querySelector('#gameWrapper').innerHTML = data;
        applyMods();
      })
      .catch(console.error);
  }, 300);
}

// Filters
function filter() {
  var ids = ['Nothing', 'Boots', 'Menus', 'Ingame', 'Playable'];
  ids.forEach(id => {
    element = document.getElementById(id + 'Bar');
    parent = element.parentElement;
    parent.addEventListener('click', function() {
      this.classList.toggle('selected');
      
      tagFilter.includes(id) ? tagFilter.splice(tagFilter.indexOf(id), 1) : tagFilter.push(id);
      console.log('Tag Filter has been updated to: ' + tagFilter);
      updatePageValue = true;
      pageNumber = 1;
      UpdateSearchResults();
    });
  });
};

// Image Handler
function imageHandler() {
  document.querySelectorAll('.gameImage').forEach(gameImage => {
    gameImage.setAttribute("loading", "lazy");
    const data = gameImage.dataset.cusa;
    gameImage.src = data.includes('CUSA') && imageLoading ? (avifSupport ? "https://fpps4.net/images/CUSA/" + data + ".avif" : "https://fpps4.net/images/NA.jpg") : (avifSupport ? "https://fpps4.net/images/NA.avif" : "https://fpps4.net/images/NA.jpg");
  });
}

// Link Handler
function linkHandler() {
  document.querySelectorAll('.gameImageLink').forEach(link => {
    link.setAttribute("target", "_blank");
    link.href = "https://github.com/red-prig/fpps4-game-compatibility/issues/" + link.dataset.id;
    link.removeAttribute('data-id');
  });
}

// Game Status Colors
function gameColors() {
  document.querySelectorAll('.gameContainer').forEach(game => {
    const status = game.querySelector('.gameStatus').textContent;
    const statusClass = (['Nothing', 'Boots', 'Ingame', 'Menus', 'Playable'].includes(status) ? status : 'Nothing');
    game.querySelector('.gameStatus').classList.add(statusClass);
    game.querySelector('.gameSeparator').classList.add(statusClass);
  });
}

// Image Effect
function imageEffect() {
  document.querySelectorAll('.gameImage').forEach(image => {
    image.addEventListener('mousemove', e => {
      const r = image.getBoundingClientRect();
      const x = e.clientX - r.left;
      const y = e.clientY - r.top;
      image.style.transformOrigin = `${x}px ${y}px`;
      image.style.transform = 'scale(1.08)';
    });
    image.addEventListener('mouseleave', () => {
      image.style.transform = 'scale(1)';
    });
  });
}

function applyMods() {
  gameColors();
  imageHandler();
  linkHandler();
  imageEffect();
  dateHandler();
  updatePageSelector();
  updateFooter()
}

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

// NoImage button
function imageButton() {
  e = document.getElementById('imageButton');
  if (noImagesLocked === true) {
    e.classList.add('selected');
    e.style.cursor = 'default';
  } else {
    clearTimeout(iTimer);
    e.classList.toggle('selected');

    imageLoading = !imageLoading;
    setCookie("imageLoading", imageLoading);
    iTimer = setTimeout(imageHandler, 450);
  }
}

// show last updated dates button
function dateButton() {
  datesButton = !datesButton;
  setCookie("datesButton", datesButton);
  dateHandler();
}

function dateHandler() {
  var gameCusaText = document.querySelectorAll(".gameCusa");
  var button = document.getElementById('datesButton');
  datesButton ? button.classList.add('selected') : button.classList.remove('selected');

  gameCusaText.forEach(element => {
    var currentText = element.textContent;
    var date = element.getAttribute('data');

    if (datesButton === true) {
      element.dataset.status = currentText;
      element.textContent = date;
    } else if (currentText === date) {
      element.textContent = element.dataset.status;
    }
  });
}

// Sorting Button
function sortButton(e) {
  clearTimeout(iTimer);
  oldestFilter = !oldestFilter;
  e.classList.toggle('selected');
  iTimer = setTimeout(UpdateSearchResults, 450);
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

const inputElement = document.getElementById('search2');
function updatePageSelector() {
  search3.classList.add('selected');
  maxValue = document.getElementById("totalPages").getAttribute('data');
  if (updatePageValue === true) {
    inputValue = 1;
    updatePageValue = false;
    document.getElementById('pageBarMin').classList.add('selected');
  }

  if (inputValue == 1) {
    document.getElementById('pageBarMin').classList.add('selected');
    search3.placeholder = '...';
    search3.classList.remove('selected');
  } else {
    document.getElementById('pageBarMin').classList.remove('selected');
  }

  if (inputValue == maxValue) {
    document.getElementById('pageBarMax').classList.add('selected');
    search3.placeholder = '...';
    search3.classList.remove('selected');
  } else {
    document.getElementById('pageBarMax').classList.remove('selected');
  }

  document.getElementById('pageBarMax').textContent = maxValue;
  inputElement.placeholder = inputValue + "/" + maxValue;

  inputElement.addEventListener('input', function(event) {
    clearTimeout(pTimer);
    inputValue = parseInt(inputElement.value, 10);

    if (isNaN(parseFloat(inputValue))) {
      inputValue = 1;
    }
    if (inputValue > maxValue) {
      inputValue = maxValue;
    } else if (inputValue < 1) {
      inputValue = 1;
    }

    inputElement.placeholder = inputValue + "/" + maxValue;
    search3.placeholder = inputValue;
    pTimer = setTimeout(() => {
      if (inputValue > 9) {
        inputElement.style.padding = "0 0.3rem 0 0.7rem";
      } else if (inputValue < 10) {
        inputElement.style.padding = "";
      }
      inputElement.value = "";
      search3.value = "";
      pageNumber = inputValue;
      UpdateSearchResults();
    }, 500);
  });
}

function pageBarBack() {
  if (pageNumber > 1) {
    clearTimeout(pbTimer);
    inputValue--;
    pageNumber--;
    document.getElementById('pageBarMax').classList.remove('selected');
    if (pageNumber == 1) {
      search3.placeholder = '...';
      search3.classList.remove('selected');
      document.getElementById('pageBarMin').classList.add('selected');
    } else {
    search3.placeholder = pageNumber;
    search3.classList.add('selected');
    }
    pbTimer = setTimeout(() => {
      UpdateSearchResults();
    }, 500);
  }
}

function pageBarForward() {
  maxValue = document.getElementById("totalPages").getAttribute('data');
  if (inputValue < maxValue) {
    clearTimeout(pfTimer);
    inputValue++;
    pageNumber++;
    document.getElementById('pageBarMin').classList.remove('selected');
    if (pageNumber == maxValue) {
      search3.placeholder = '...';
      search3.classList.remove('selected');
      document.getElementById('pageBarMax').classList.add('selected');
    } else {
      search3.placeholder = inputValue;
      search3.classList.add('selected');
    }
    pfTimer = setTimeout(() => {
      UpdateSearchResults();
    }, 500);
  }
}

function pageBarMax() {
  clearTimeout(pmaxTimer);
  document.getElementById('pageBarMax').classList.add('selected');
  document.getElementById('pageBarMin').classList.remove('selected');
  inputValue = maxValue;
  search3.placeholder = '...';
  search3.classList.remove('selected');
  pageNumber = maxValue;
  pmaxTimer = setTimeout(() => {
    UpdateSearchResults();
  }, 400);
}

function pageBarMin() {
  clearTimeout(pminTimer);
  document.getElementById('pageBarMin').classList.add('selected');
  document.getElementById('pageBarMax').classList.remove('selected');
  search3.placeholder = '...';
  search3.classList.remove('selected');
  inputValue = 1;
  pageNumber = 1;
  pminTimer = setTimeout(() => {
    UpdateSearchResults();
    search3.placeholder = '...';
  }, 400);
}

const search3 = document.getElementById("search3");

search3.addEventListener("click", function() {
  if (search3.placeholder == '...') {
    search3.placeholder = ''; 
  }
});

search3.addEventListener("blur", function() {
  if (search3.placeholder == '') {
    search3.placeholder = '...'; 
  }
});

search3.addEventListener('input', function(event) {
  clearTimeout(pTimer);
  inputValue = parseInt(search3.value, 10);
  document.getElementById('pageBarMax').classList.remove('selected');
  document.getElementById('pageBarMin').classList.remove('selected');

  if (isNaN(parseFloat(inputValue))) {
    inputValue = 1;
  }
  if (inputValue > maxValue) {
    inputValue = maxValue;
  } else if (inputValue < 1) {
    inputValue = 1;
  }

  inputElement.placeholder = inputValue + "/" + maxValue;
  search3.placeholder = inputValue;
  pTimer = setTimeout(() => {
    if (inputValue > 9) {
      inputElement.style.padding = "0 0.3rem 0 0.7rem";
    } else if (inputValue < 10) {
      inputElement.style.padding = "";
    }
    inputElement.value = "";
    search3.value = "";
    search3.classList.add('selected');
    pageNumber = inputValue;
    UpdateSearchResults();
  }, 500);
});

// Footer Load
fetch('https://fpps4.net/parts/footer.html')
.then(response => response.text())
.then(data => {
  document.querySelector('#footer').innerHTML = data;
})
.catch(console.error);