function adjustSizes() {
  var screenWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
  var referenceFontSize = 16;
  var phoneReferenceWidth = 560;

  if (screenWidth >= phoneReferenceWidth) {
    document.documentElement.style.fontSize = referenceFontSize + 'px';
  } else {
    var fontSize = (screenWidth / phoneReferenceWidth) * referenceFontSize;
    document.documentElement.style.fontSize = fontSize + 'px';
  }
}

window.addEventListener('DOMContentLoaded', adjustSizes);
window.addEventListener('resize', adjustSizes);