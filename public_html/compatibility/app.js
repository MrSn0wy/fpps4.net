// this is my attempt on rewriting this... thing
let avifSupport = false;
let imageLoading = true;
let oldestFilter = false;
let datesButton = false;
let tagFilter = [];
let pageNumber = 1;

let Timer;
let totalPages;
let totalTime;
let totalIssues;
let fancyJsonData; // :3


// Fetch html for footer and header
async function fetchHtml(path, target){
  fetch(path).then(response => response.text()).then(data => {
    document.querySelector(target).innerHTML = data;
  }).catch(console.error);
}


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
  // window.alert("Hey! Your browser doesnt support avif, avif is an image format that has low file sizes while having high quality images. You won't get any game images");
  const iButton = document.getElementById('imageButton');
  console.log(iButton);
  iButton.classList.add('selected');
  iButton.style.cursor = 'default';
  iButton.removeAttribute("onclick");
};



// game cards and stats handler and other onload stuff
document.addEventListener('DOMContentLoaded', function() {
  // + check cookies
  const imageLoadingCookie = checkCookies("imageLoadingSetting", "true"); 
  const datesSetting = checkCookies("datesSetting", "false");

  if (imageLoadingCookie === "false") {
    imageLoading = false;
    document.getElementById("imageButton").classList.add('selected');
  }

  if (datesSetting === "true") {
    datesButton = true;
    document.getElementById('datesButton').classList.toggle('selected');
  }

  // Adjust screen size for mobile and 4k monitors for some reason
  window.addEventListener('load', adjustScreenSize);
  window.addEventListener('resize', adjustScreenSize);

  
  // + fetch issues and set the tag bars
  fetch('/_scripts/search.php?q=&stats')
  .then(response => response.json())
  .then(jsonData => {
    gameCardHandler(jsonData);
    fancyJsonData = jsonData;
    pageButtonHandler();

    totalPages = jsonData.info.pages;
    totalTime = jsonData.info.time;
    totalIssues = jsonData.info.issues;
    console.log("\nCOMPATIBILITY STATS");

    // stats & tag filter
    jsonData.stats.forEach(stat => {
      var tag = stat.tag;
      var percent = stat.percent;
      var element = document.getElementById(tag + 'Bar');
      var textElement = document.getElementById(tag + 'Info')
      var parentElement = element.parentElement;
      console.log(`${tag} = ${percent}% [${stat.count}]`);
      element.style.width = percent + '%';
      textElement.textContent = percent + '% - ' + stat.count;
      
      parentElement.addEventListener('click', function() {
        parentElement.classList.toggle('selected');
        tagFilter.includes(tag) ? tagFilter.splice(tagFilter.indexOf(tag), 1) : tagFilter.push(tag);
        pageNumber = 1;
        updateSearchResults();
      });
    });
    console.log("\n");
  })
  .catch(console.error);
});


// Header & Footer Load
fetchHtml("/_parts/navbar.html", "#header");
fetchHtml("/_parts/footer.html", "#footer");


// Searching
document.querySelector('#search').addEventListener('input', function() {
  pageNumber = 1;
  updateSearchResults();
});

async function updateSearchResults() {
  clearTimeout(Timer);
  const gameWrapper = document.querySelector('#gameWrapper');
  const searchQuery = document.querySelector('#search').value;
  pageButtonHandler();

  gameWrapper.querySelectorAll('.gameContainer').forEach(container => {
    const skeletonDiv = document.createElement('div');
    skeletonDiv.classList.add('gameContainer', 'skeletonAnimation');
    gameWrapper.replaceChild(skeletonDiv, container);
  });
    
  Timer = setTimeout(() => {
    fetch('/_scripts/search.php?q=' + searchQuery + '&tag=' + tagFilter + '&page=' + pageNumber + '&oldest=' + oldestFilter)
    .then(response => response.json())
    .then(jsonData => {
      fancyJsonData = jsonData;
      gameCardHandler(jsonData);
    })
    .catch(console.error);
  }, 300);
}

// Game Card handler
async function gameCardHandler(jsonData) {
  const gameWrapper = document.getElementById("gameWrapper");
  gameWrapper.innerHTML = "";

  jsonData.games.forEach(game => {
    // game image URL
    let imageSource = "";
    let imageText = "N/A";
    let imageTextSize = 1.38;

    switch(true) {
      case game.image && imageLoading && avifSupport && game.type === "HB":
        imageSource = "/_images/HB/" + game.title + ".avif";
      break;
      case game.image && imageLoading && avifSupport:
        imageSource = "/_images/CUSA/" + game.code +".avif";
      break;
      case game.type === "SYS":
        imageText = "SYSTEM";
        imageTextSize = 1.13;
      break
    }

    if (game.type === "HB") { // needs to be applied to all homebrews
      imageText = "HOME<br>BREW";
      imageTextSize = 1.25;
    }

    let imageTextEnabled = game.image && imageLoading && avifSupport ? "none" : "flex";

    // game cards
    const gameElementHTML = `
    <div class="gameContainer">
      <a class="gameImageLink" target="_blank" href="https://github.com/red-prig/fpps4-game-compatibility/issues/${game.id}">
        <p class="gameImageText" style="font-size: ${imageTextSize}rem; display: ${imageTextEnabled};">${imageText}</p>
        ${imageSource ? `<img class="gameImage" loading="lazy" alt="${game.title} - ${game.code} game image" src="${imageSource}">` : "" }
      </a>
      <div class="gameSeparator ${game.tag}"></div>
      <div class="gameDetails">
        <p class="gameName">${game.title}</p>
        <p class="gameCusa" data-date="${game.upDate}" data-cusa="${game.code}">${game.code}</p>
        <p class="gameStatus ${game.tag}">${game.tag}</p>
      </div>
    </div>`;
    
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = gameElementHTML;
    const gameContainer = tempContainer.querySelector('.gameContainer');
    gameWrapper.appendChild(gameContainer);
  });

  const statContainer = document.createElement('h4');
  const stat = `<h4 class="totalTimeText">${jsonData.info.issues} results in ${jsonData.info.time}ms </h4>`;
  statContainer.innerHTML = stat;
  gameWrapper.appendChild(statContainer);
  if (datesButton) {
    dateButtonHandler();
  }

  // Image Effect
  document.querySelectorAll('.gameImage, .gameImageText').forEach(image => {
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
  // functions in required.js
  updateFooter();
  LightModeIconChange();
}


// NoImage button
function imageButton(button) {
  imageLoading = !imageLoading;
  setCookie("imageLoadingSetting", imageLoading);
  button.classList.toggle('selected');
  imageButtonHandler();
}


// Date Button
function dateButton(button) {
  datesButton = !datesButton;
  setCookie("datesSetting", datesButton);
  button.classList.toggle('selected');
  dateButtonHandler();
}


// Oldes/Newest Button
function sortButton(button) {
  oldestFilter = !oldestFilter;
  button.classList.toggle('selected');
  updateSearchResults();
}


async function imageButtonHandler() {  
  if (imageLoading === false) {
    document.querySelectorAll(".gameImageText").forEach(imageText => {
      imageText.style.display = "flex";
    });
  } else {
    gameCardHandler(fancyJsonData);
  }
}


async function dateButtonHandler() {
  document.querySelectorAll(".gameCusa").forEach(element => {
    if (datesButton === true) {
      element.textContent = element.dataset.date;
    } else {
      element.textContent = element.dataset.cusa;
    }
  });
}

function pageButtonHandler(type) {
  // maybe make it handle everything in once?
  const maxNumber = fancyJsonData.info.pages;
  const searchBar = document.getElementById("search3");
  const minButton = document.getElementById("pageBarMin");
  const maxButton = document.getElementById("pageBarMax");

  if (pageNumber != maxNumber) {
    if (type === "forward") {
      pageNumber += 1;
      updateSearchResults();
    } else if (type === "max") {
      pageNumber = maxNumber;
      updateSearchResults();
    }
  }

  if (pageNumber != 1) {
    if (type === "back") {
      pageNumber -= 1;
      updateSearchResults();
    } else if (type === "min") {
      pageNumber = 1;
      updateSearchResults();
    }
  }

  if (type === "input") {
    clearTimeout(Timer);
    inputValue = parseInt(search3.value, 10);
    Timer = setTimeout(() => {
      
      console.log(inputValue);
        pageNumber = inputValue;
        updateSearchResults();
        searchBar.value = "";
      // if (inputValue > maxNumber) {
      //   pageNumber = inputValue;
      //   updateSearchResults();
      //   searchBar.value = "";
      // } else {
      //   pageNumber = maxNumber;
      //   updateSearchResults();
      //   searchBar.value = "";
      // }
    }, 600);
  }

  maxButton.textContent = maxNumber;
  // searchBar.value = "";

  if (pageNumber != 1 && pageNumber != maxNumber) {
    searchBar.placeholder = pageNumber;
    searchBar.classList.add("selected");
    maxButton.classList.remove("selected");
    minButton.classList.remove("selected");
  } else if (pageNumber === 1) {
    minButton.classList.add("selected");
    maxButton.classList.remove("selected");
    searchBar.classList.remove("selected");
    searchBar.placeholder = "...";
    searchBar.value = "";
  } else if (pageNumber === maxNumber) {
    minButton.classList.remove("selected");
    maxButton.classList.add("selected");
    searchBar.classList.remove("selected");
    searchBar.placeholder = "...";
    searchBar.value = "";
  }
}

search3.addEventListener("click", async function() {
  if (search3.placeholder == '...') {
    search3.placeholder = ''; 
  }
});

search3.addEventListener("blur", async function() {
  if (search3.placeholder == '') {
    search3.placeholder = '...'; 
  }
});

// search3.addEventListener('input', async function() {
//   clearTimeout(Timer);
//   inputValue = parseInt(search3.value, 10);
//   console.log(inputValue);
// });