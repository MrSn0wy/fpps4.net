// this is my attempt on rewriting this... thing
let avifSupport = false;
let imageLoading = true;
let oldestFilter = false;
let datesButton = false;
let statusFilter = [];
let currentPage = 1;

let issuesPerPage = 20;
const codeRegex = /[a-zA-Z]{4}[0-9]{5}/;

let Timer;
let totalPages;
let totalIssues;
let fancyJsonData; // :3

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
  // window.alert("Hey! Your browser doesn't support avif, avif is an image format that has low file sizes while having high quality images. You won't get any game images");
  const iButton = document.getElementById('imageButton');
  console.log(iButton);
  iButton.classList.add('selected');
  iButton.style.cursor = 'default';
  iButton.removeAttribute("onclick");
};



// game cards and stats handler and other on-load stuff
document.addEventListener('DOMContentLoaded', async function () {
  await init()

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
  // window.addEventListener('load', adjustScreenSize);
  window.addEventListener('resize', adjustScreenSize);


  // + fetch issues and set the tag bars
  fetch('https://api.fpps4.net/database.json')
      .then(response => response.json())
      .then(jsonData => {
        fancyJsonData = jsonData;

        totalIssues = jsonData.length;
        totalPages = Math.ceil(totalIssues / issuesPerPage);

        console.log("\nCOMPATIBILITY STATS");

        let availableStatus = ['Nothing', 'Boots', 'Menus', 'Ingame', 'Playable'];
        let totalPercentage = 0;

        let statusPercentages = [];
        let statusCount = [];

        availableStatus.forEach(status => {
          statusCount[status] = 0; // init tag count

          jsonData.forEach(issue => {
            if (issue.status === status) {
              statusCount[status]++;
            }
          });

          let rawPercentage = parseFloat((statusCount[status] / totalIssues * 100).toFixed(2));

          statusPercentages[status] = rawPercentage;
          totalPercentage += rawPercentage;
        });

        // stats & tag filter
        availableStatus.forEach(status => {
          let percent = statusPercentages[status];
          let count = statusCount[status];
          let element = document.getElementById(status + 'Bar');
          let textElement = document.getElementById(status + 'Info')
          let parentElement = element.parentElement;
          console.log(`${status} = ${percent}% [${count}]`);
          element.style.width = percent + '%';
          textElement.textContent = percent + '% - ' + count;

          parentElement.addEventListener('click', function () {
            parentElement.classList.toggle('selected');
            statusFilter.includes(status) ? statusFilter.splice(statusFilter.indexOf(status), 1) : statusFilter.push(status);
            currentPage = 1;
            updateSearchResults();
          });
        });
        console.log("\n");
        
        gameCardHandler(jsonData.slice(0, issuesPerPage)); //first 20
        pageButtonHandler();

      })
      .catch(console.error);
});


// Searching
document.querySelector('#search').addEventListener('input', function() {
  currentPage = 1;
  updateSearchResults();
});

function updateSearchResults() {
  clearTimeout(Timer);
  const gameWrapper = document.querySelector('#gameWrapper');
  const searchQuery = document.querySelector('#search').value.toLowerCase();

  gameWrapper.querySelectorAll('.gameContainer').forEach(container => {
    const skeletonDiv = document.createElement('div');
    skeletonDiv.classList.add('gameContainer', 'skeletonAnimation');
    gameWrapper.replaceChild(skeletonDiv, container);
  });

  Timer = setTimeout(() => {
    let jsonData = [];
    let isCodeSearch = codeRegex.test(searchQuery);

    fancyJsonData.forEach(issue => {
      // id based searching
      if (isCodeSearch && (issue.code.toLowerCase() !== searchQuery)) {
        return;

        // title based searching
      } else if (!isCodeSearch && !issue.title.toLowerCase().includes(searchQuery)) {
        return;
      }

      // filter tags
      if (statusFilter.length > 0) {
        let isGood = false;

        for (const status of statusFilter) {
          if (status === issue.status) {
            isGood = true;
            break;
          }
        }

        if (isGood === false) {
          return;
        }
      }

      jsonData.push(issue);
    });


    let startSlice = (currentPage - 1) * issuesPerPage; // makes it start on 0
    let endSlice = startSlice + issuesPerPage;

    totalPages = Math.ceil(jsonData.length / issuesPerPage);
    totalIssues = jsonData.length;

    let tempJsonData;

    if (oldestFilter === true) {
      tempJsonData = jsonData.reverse();
    } else {
      tempJsonData = jsonData;
    }
    
    gameCardHandler(tempJsonData.slice(startSlice, endSlice));
    pageButtonHandler();

  }, 300);
}

// Game Card handler
function gameCardHandler(jsonData) {
  const gameWrapper = document.getElementById("gameWrapper");
  gameWrapper.innerHTML = "";

  jsonData.forEach(issue => {
    // game image URL
    let imageSource = "";
    let imageText = "GAME";
    let imageTextSize = 1.25;

    if (issue.title === "Sonic Time Twisted") {
      console.log(issue.issue_type)
    }
    
    switch(true) {
      case issue.image && imageLoading && avifSupport && issue.issue_type === "Homebrew":
        imageSource = "https://api.fpps4.net/images/homebrew/" + issue.title + ".avif";
      break;
      case issue.image && imageLoading && avifSupport:
        imageSource = "https://api.fpps4.net/images/game/" + issue.code +".avif";
      break;
      case issue.issue_type === "SystemFwUnknown" || issue.issue_type === "SystemFw505":
        imageText = "SYSTEM";
        imageTextSize = 1.13;
      break
    }

    if (issue.issue_type === "Homebrew") { // needs to be applied to all homebrews
      imageText = "HOME<br>BREW";
      imageTextSize = 1.25;
      if (issue.code === "") {
        issue.code = "HOMEBREW";
      }
    }

    let imageTextEnabled = issue.image && imageLoading && avifSupport ? "none" : "flex";
    let updated =  new Date(issue.updated).toLocaleDateString()
    
    
    // game cards
    const gameElementHTML = `
    <div class="gameContainer">
      <a class="gameImageLink" target="_blank" href="https://github.com/red-prig/fpps4-game-compatibility/issues/${issue.id}">
        <p class="gameImageText" style="font-size: ${imageTextSize}rem; display: ${imageTextEnabled};">${imageText}</p>
        ${imageSource ? `<img class="gameImage" loading="lazy" alt="${issue.title} - ${issue.code} game image" src="${imageSource}">` : "" }
      </a>
      <div class="gameSeparator ${issue.status}"></div>
      <div class="gameDetails">
        <p class="gameName">${issue.title}</p>
        <p class="gameCusa" data-date="${updated}" data-cusa="${issue.code}">${issue.code}</p>
        <p class="gameStatus ${issue.status}">${issue.status}</p>
      </div>
    </div>`;

    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = gameElementHTML;
    const gameContainer = tempContainer.querySelector('.gameContainer');
    gameWrapper.appendChild(gameContainer);
  });

  const statContainer = document.createElement('h4');
  statContainer.innerHTML = `<h4 class="totalTimeText">${totalIssues} results </h4>`;
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


function imageButtonHandler() {
  if (imageLoading === false) {
    document.querySelectorAll(".gameImageText").forEach(imageText => {
      imageText.style.display = "flex";
    });
  } else {
    updateSearchResults();
  }
}


function dateButtonHandler() {
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
  const maxNumber = totalPages;
  const searchBar = document.getElementById("search3");
  const minButton = document.getElementById("pageBarMin");
  const maxButton = document.getElementById("pageBarMax");

  maxButton.textContent = maxNumber;

  if (currentPage != maxNumber) {
    if (type === "forward") {
      currentPage += 1;
      updateSearchResults();
    } else if (type === "max") {
      currentPage = maxNumber;
      updateSearchResults();
    }
  }

  if (currentPage != 1) {
    if (type === "back") {
      currentPage -= 1;
      updateSearchResults();
    } else if (type === "min") {
      currentPage = 1;
      updateSearchResults();
    }
  }

  if (type === "input") {
    clearTimeout(Timer);

    Timer = setTimeout(() => {
      inputValue = parseInt(searchBar.value);
      console.log(inputValue);

      if (inputValue > maxNumber) {
        inputValue = maxNumber;
      } else if (inputValue < 1) {
        inputValue = 1;
      }

      currentPage = inputValue;
      searchBar.placeholder = currentPage;
      searchBar.value = "";
      updateSearchResults();
    }, 400);
  }



  if (currentPage !== 1 && currentPage !== maxNumber) {
    searchBar.placeholder = currentPage;
    searchBar.classList.add("selected");
    maxButton.classList.remove("selected");
    minButton.classList.remove("selected");
  } else if (currentPage === 1) {
    minButton.classList.add("selected");
    maxButton.classList.remove("selected");
    searchBar.classList.remove("selected");
    searchBar.placeholder = "...";
  } else if (currentPage === maxNumber) {
    minButton.classList.remove("selected");
    maxButton.classList.add("selected");
    searchBar.classList.remove("selected");
    searchBar.placeholder = "...";
  }
}

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