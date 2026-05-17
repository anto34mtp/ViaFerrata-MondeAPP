import React, {useRef, useState, useEffect} from 'react';
import {
  View,
  ActivityIndicator,
  Text,
  StyleSheet,
  Platform,
} from 'react-native';
import {WebView} from 'react-native-webview';
import {useNavigation} from '@react-navigation/native';
import {getMapVias, MapPoint} from '../api/client';

const MapScreen: React.FC = () => {
  const navigation = useNavigation<any>();
  const webRef = useRef<WebView>(null);
  const [webLoading, setWebLoading] = useState(true);
  const [points, setPoints] = useState<MapPoint[]>([]);
  const [htmlReady, setHtmlReady] = useState(false);

  useEffect(() => {
    getMapVias()
      .then(res => {
        const raw = res.data as any;
        const body = (raw && typeof raw === 'object' && 'ok' in raw && 'data' in raw) ? raw.data : raw;
        setPoints(Array.isArray(body) ? body : []);
      })
      .catch(() => setPoints([]))
      .finally(() => setHtmlReady(true));
  }, []);

  const handleMessage = (event: {nativeEvent: {data: string}}) => {
    try {
      const msg = JSON.parse(event.nativeEvent.data);
      if (msg.type === 'via' && msg.slug) {
        navigation.navigate('ViaDetail', {slug: msg.slug, name: msg.name ?? msg.slug});
      }
    } catch {}
  };

  if (!htmlReady) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#2E7D32" />
        <Text style={styles.loadingText}>Chargement des vias…</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <WebView
        ref={webRef}
        source={{html: buildMapHTML(points)}}
        style={styles.webview}
        javaScriptEnabled
        domStorageEnabled
        geolocationEnabled
        originWhitelist={['*']}
        onMessage={handleMessage}
        onLoadEnd={() => setWebLoading(false)}
        onError={() => setWebLoading(false)}
        mixedContentMode={Platform.OS === 'android' ? 'always' : undefined}
      />
      {webLoading && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color="#2E7D32" />
          <Text style={styles.loadingText}>Chargement de la carte…</Text>
        </View>
      )}
    </View>
  );
};

function buildMapHTML(points: MapPoint[]): string {
  const viasJson = JSON.stringify(points);
  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    html,body,#map{width:100%;height:100%;overflow:hidden}
    #controls{position:absolute;top:56px;right:8px;z-index:1000;display:flex;flex-direction:column;gap:5px}
    .ctrl-btn{background:white;border:1px solid rgba(0,0,0,0.25);border-radius:6px;padding:7px 11px;font-size:13px;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,0.18);font-family:sans-serif;white-space:nowrap;touch-action:manipulation}
    .ctrl-btn.active{background:#2E7D32;color:white;border-color:#2E7D32}
    #locate-btn{background:#2E7D32;color:white;border-color:#2E7D32;font-weight:700}
    .leaflet-popup-content{margin:8px 10px;font-family:sans-serif}
  </style>
</head>
<body>
<div id="map"></div>
<div id="controls">
  <button class="ctrl-btn" id="locate-btn" onclick="locateMe()">📍 Me localiser</button>
  <button class="ctrl-btn" id="r25" onclick="toggleRadius(25)">25 km</button>
  <button class="ctrl-btn" id="r50" onclick="toggleRadius(50)">50 km</button>
  <button class="ctrl-btn" id="r100" onclick="toggleRadius(100)">100 km</button>
  <button class="ctrl-btn" id="r200" onclick="toggleRadius(200)">200 km</button>
</div>
<script>
(function(){
  var VIAS = ${viasJson};
  var map = L.map('map',{zoomControl:true}).setView([46.5,2.3],6);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains:'abcd',maxZoom:19
  }).addTo(map);

  var DIFF_COLORS=['#1B5E20','#388E3C','#66BB6A','#FDD835','#FB8C00','#E53935','#B71C1C'];
  function diffColor(d){
    if(d==null)return'#607D8B';
    return DIFF_COLORS[Math.min(6,Math.max(0,Math.round((d-1)*6/9)))];
  }

  VIAS.forEach(function(v){
    if(!v.gps_lat||!v.gps_lng)return;
    var m=L.circleMarker([v.gps_lat,v.gps_lng],{
      radius:7,fillColor:diffColor(v.difficulty),color:'#fff',
      weight:1.5,opacity:1,fillOpacity:0.88
    }).addTo(map);
    var diff=v.difficulty?'<br><small style="color:#666">Difficulté: '+v.difficulty+'/10</small>':'';
    var rat=v.avg_overall?'<br><small style="color:#666">⭐ '+v.avg_overall+'</small>':'';
    var slug=(v.slug||'').replace(/\\\\/g,'\\\\\\\\').replace(/'/g,"\\\\'");
    var nm=(v.name||'').replace(/\\\\/g,'\\\\\\\\').replace(/'/g,"\\\\'");
    m.bindPopup('<div style="min-width:155px"><b style="font-size:14px">'+v.name+'</b>'+diff+rat+'<br><button onclick="openVia(\\''+slug+'\\',\\''+nm+'\\');" style="margin-top:8px;background:#2E7D32;color:#fff;border:none;padding:6px 10px;border-radius:6px;width:100%;font-size:13px;cursor:pointer">Voir le détail ›</button></div>');
  });

  var userMarker=null,circles={};

  function drawCircles(lat,lng){
    Object.keys(circles).forEach(function(k){
      map.removeLayer(circles[k]);
      circles[k]=L.circle([lat,lng],{
        radius:parseInt(k)*1000,
        color:'#2E7D32',fillColor:'#4CAF50',fillOpacity:0.04,weight:2,dashArray:'6 10'
      }).addTo(map);
    });
  }

  function locateMe(){
    if(!navigator.geolocation){alert('Géolocalisation non disponible');return;}
    document.getElementById('locate-btn').textContent='⏳ Localisation…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat=pos.coords.latitude,lng=pos.coords.longitude;
      document.getElementById('locate-btn').textContent='📍 Me localiser';
      if(userMarker)map.removeLayer(userMarker);
      userMarker=L.marker([lat,lng],{
        icon:L.divIcon({
          html:'<div style="width:16px;height:16px;background:#2196F3;border:3px solid #fff;border-radius:50%;box-shadow:0 0 8px rgba(33,150,243,0.7)"></div>',
          iconSize:[16,16],iconAnchor:[8,8],className:''
        })
      }).addTo(map).bindPopup('Vous êtes ici');
      map.setView([lat,lng],10);
      drawCircles(lat,lng);
    },function(err){
      document.getElementById('locate-btn').textContent='📍 Me localiser';
      alert('Localisation impossible: '+err.message);
    },{enableHighAccuracy:true,timeout:15000,maximumAge:60000});
  }

  function toggleRadius(km){
    var btn=document.getElementById('r'+km);
    if(circles[km]){
      map.removeLayer(circles[km]);
      delete circles[km];
      btn.classList.remove('active');
      return;
    }
    if(!userMarker){
      locateMe();
      var tries=0;
      var wait=setInterval(function(){
        if(userMarker||tries>30){
          clearInterval(wait);
          if(!userMarker)return;
          var ll=userMarker.getLatLng();
          circles[km]=L.circle([ll.lat,ll.lng],{radius:km*1000,color:'#2E7D32',fillColor:'#4CAF50',fillOpacity:0.04,weight:2,dashArray:'6 10'}).addTo(map);
          btn.classList.add('active');
        }
        tries++;
      },500);
      return;
    }
    var ll=userMarker.getLatLng();
    circles[km]=L.circle([ll.lat,ll.lng],{radius:km*1000,color:'#2E7D32',fillColor:'#4CAF50',fillOpacity:0.04,weight:2,dashArray:'6 10'}).addTo(map);
    btn.classList.add('active');
  }

  function openVia(slug,name){
    try{window.ReactNativeWebView.postMessage(JSON.stringify({type:'via',slug:slug,name:name}));}catch(e){}
  }
})();
</script>
</body>
</html>`;
}

const styles = StyleSheet.create({
  container: {flex: 1},
  webview: {flex: 1},
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(245,245,245,0.9)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {marginTop: 12, fontSize: 15, color: '#666'},
});

export default MapScreen;
