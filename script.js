const indicator = document.querySelector(".indicator");
const lis = document.querySelectorAll(".header-nav li");

lis.forEach(function(li){ 
    if(li.classList.contains('active')) {
        const value = li.getAttribute("aria-label");
        indicator.style.left = value;
        document.documentElement.style.setProperty("--sliding-indicator",value)
    }
})

const map = L.map("mapSection").setView([-8.94197016499707, 33.41606482191847],10);

L.tileLayer(
     "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
     {
        attribution: 'Esri'
     }
).addTo(map);

const customIcon = L.icon({
    iconUrl: './images/customIcon.png',
    iconSize: [50,50],
    iconAnchor: [15, 45],
    popupAnchor: [10, -40]
});

L.marker([-8.94197016499707, 33.41606482191847],
    {icon: customIcon}
).addTo(map)
 .bindPopup("Iyunga Secondary❤️.")
 .openPopup();

setTimeout(() => {
    map.invalidatesize();
},200)
