import React, {useRef, useState, useEffect, useCallback} from 'react';
import {
  View,
  ActivityIndicator,
  Text,
  StyleSheet,
  Platform,
  PermissionsAndroid,
  Alert,
  Linking,
  TouchableOpacity,
} from 'react-native';
import {WebView} from 'react-native-webview';
import {useNavigation} from '@react-navigation/native';
import Geolocation from '@react-native-community/geolocation';
import {getMapVias, MapPoint} from '../api/client';

const KM_BUTTONS = [25, 50, 100, 200];

const MapScreen: React.FC = () => {
  const navigation = useNavigation<any>();
  const webRef = useRef<WebView>(null);
  const [webLoading, setWebLoading] = useState(true);
  const [points, setPoints] = useState<MapPoint[]>([]);
  const [htmlReady, setHtmlReady] = useState(false);
  const [locating, setLocating] = useState(false);
  const [activeKm, setActiveKm] = useState<number | null>(null);
  const userPos = useRef<{lat: number; lng: number} | null>(null);

  useEffect(() => {
    getMapVias()
      .then(res => {
        const raw = res.data as any;
        const body =
          raw && typeof raw === 'object' && 'ok' in raw && 'data' in raw
            ? raw.data
            : raw;
        setPoints(Array.isArray(body) ? body : []);
      })
      .catch(() => setPoints([]))
      .finally(() => setHtmlReady(true));
  }, []);

  // Request permission proactively when the map screen opens
  useEffect(() => {
    if (Platform.OS !== 'android') return;
    (async () => {
      const perm = PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION;
      const already = await PermissionsAndroid.check(perm);
      if (already) return;
      const result = await PermissionsAndroid.request(perm, {
        title: 'Localisation requise',
        message:
          'ViaFerrata Monde a besoin de votre position pour afficher les vias proches de vous.',
        buttonPositive: 'Autoriser',
        buttonNegative: 'Plus tard',
      });
      if (result === PermissionsAndroid.RESULTS.NEVER_ASK_AGAIN) {
        Alert.alert(
          'Permission GPS bloquée',
          "La localisation a été refusée définitivement. Activez-la dans les paramètres de l'application.",
          [
            {text: 'Annuler', style: 'cancel'},
            {
              text: 'Ouvrir les paramètres',
              onPress: () => Linking.openSettings(),
            },
          ],
        );
      }
    })();
  }, []);

  // Core position getter — checks permission then resolves GPS
  const getPosition = useCallback(
    (onSuccess?: (lat: number, lng: number) => void) => {
      const doFetch = () => {
        Geolocation.getCurrentPosition(
          pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            userPos.current = {lat, lng};
            webRef.current?.injectJavaScript(
              `setUserLocation(${lat}, ${lng}); true;`,
            );
            setLocating(false);
            onSuccess?.(lat, lng);
          },
          err => {
            setLocating(false);
            Alert.alert('GPS indisponible', err.message ?? 'Erreur inconnue');
          },
          {enableHighAccuracy: true, timeout: 15000, maximumAge: 60000},
        );
      };

      if (Platform.OS === 'android') {
        PermissionsAndroid.check(
          PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
        ).then(granted => {
          if (granted) {
            doFetch();
          } else {
            setLocating(false);
            Alert.alert(
              'Permission GPS requise',
              "Activez la localisation dans les paramètres de l'application.",
              [
                {text: 'Annuler', style: 'cancel'},
                {
                  text: 'Ouvrir les paramètres',
                  onPress: () => Linking.openSettings(),
                },
              ],
            );
          }
        });
      } else {
        doFetch();
      }
    },
    [],
  );

  const handleLocate = useCallback(() => {
    if (locating) return;
    setLocating(true);
    getPosition();
  }, [locating, getPosition]);

  const handleRadius = useCallback(
    (km: number) => {
      if (activeKm === km) {
        // Toggle off
        setActiveKm(null);
        webRef.current?.injectJavaScript(`removeCircle(${km}); true;`);
        return;
      }
      // Remove previous circle, select new one
      if (activeKm !== null) {
        webRef.current?.injectJavaScript(`removeCircle(${activeKm}); true;`);
      }
      setActiveKm(km);
      if (userPos.current) {
        const {lat, lng} = userPos.current;
        webRef.current?.injectJavaScript(
          `addCircle(${km}, ${lat}, ${lng}); true;`,
        );
      } else {
        setLocating(true);
        getPosition((lat, lng) => {
          webRef.current?.injectJavaScript(
            `addCircle(${km}, ${lat}, ${lng}); true;`,
          );
        });
      }
    },
    [activeKm, getPosition],
  );

  const handleMessage = useCallback(
    (event: {nativeEvent: {data: string}}) => {
      try {
        const msg = JSON.parse(event.nativeEvent.data);
        if (msg.type === 'via' && msg.slug) {
          navigation.navigate('ViaDetail', {
            slug: msg.slug,
            name: msg.name ?? msg.slug,
          });
        }
      } catch {}
    },
    [navigation],
  );

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
        originWhitelist={['*']}
        onMessage={handleMessage}
        onLoadEnd={() => setWebLoading(false)}
        onError={() => setWebLoading(false)}
        mixedContentMode={Platform.OS === 'android' ? 'always' : undefined}
      />

      {/* Native RN overlay — avoids WebView touch-event issues */}
      <View style={styles.controls} pointerEvents="box-none">
        <TouchableOpacity
          style={styles.locateBtn}
          onPress={handleLocate}
          activeOpacity={0.75}>
          {locating ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <Text style={styles.locateBtnText}>📍 Me localiser</Text>
          )}
        </TouchableOpacity>
        {KM_BUTTONS.map(km => (
          <TouchableOpacity
            key={km}
            style={[styles.kmBtn, activeKm === km && styles.kmBtnActive]}
            onPress={() => handleRadius(km)}
            activeOpacity={0.75}>
            <Text
              style={[
                styles.kmBtnText,
                activeKm === km && styles.kmBtnTextActive,
              ]}>
              {km} km
            </Text>
          </TouchableOpacity>
        ))}
      </View>

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
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    html,body,#map{width:100%;height:100%;overflow:hidden}
    .leaflet-popup-content{margin:8px 10px;font-family:sans-serif}
    .marker-cluster-small div,.marker-cluster-medium div,.marker-cluster-large div{font-weight:700}
  </style>
</head>
<body>
<div id="map"></div>
<script>
(function(){
  var VIAS = ${viasJson};
  var map = L.map('map',{zoomControl:true,clickTolerance:10}).setView([46.5,2.3],6);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains:'abcd',maxZoom:19
  }).addTo(map);

  var DIFF_COLORS=['#1B5E20','#388E3C','#66BB6A','#FDD835','#FB8C00','#E53935','#B71C1C'];
  function diffColor(d){
    if(d==null)return'#607D8B';
    return DIFF_COLORS[Math.min(6,Math.max(0,Math.round((d-1)*6/9)))];
  }

  var cluster = L.markerClusterGroup({
    maxClusterRadius: 40,
    showCoverageOnHover: false,
    disableClusteringAtZoom: 12,
  });

  VIAS.forEach(function(v){
    if(!v.gps_lat||!v.gps_lng)return;
    var slug = v.slug || '';
    var name = v.name || '';
    var m=L.circleMarker([v.gps_lat,v.gps_lng],{
      radius:9,fillColor:diffColor(v.difficulty),color:'#fff',
      weight:2,opacity:1,fillOpacity:0.9
    });
    var diff=v.difficulty?'<br><small style="color:#666">Difficulté: '+v.difficulty+'/10</small>':'';
    m.bindPopup(
      '<div style="min-width:155px">'+
      '<b style="font-size:14px">'+name+'</b>'+diff+
      '<br><button class="via-btn" style="margin-top:8px;background:#2E7D32;color:#fff;border:none;padding:6px 10px;border-radius:6px;width:100%;font-size:13px;cursor:pointer;">Voir le détail ›</button>'+
      '</div>'
    );
    m.on('popupopen', function(e){
      var el = e.popup.getElement();
      if(!el) return;
      var btn = el.querySelector('.via-btn');
      if(!btn) return;
      ['click','touchend'].forEach(function(evtName){
        btn.addEventListener(evtName, function(ev){
          ev.preventDefault();
          ev.stopPropagation();
          openVia(slug, name);
        });
      });
    });
    cluster.addLayer(m);
  });
  map.addLayer(cluster);

  var userMarker = null;
  var circles = {};

  function circleOptions(km){
    return {radius:km*1000,color:'#2E7D32',fillColor:'#4CAF50',fillOpacity:0.05,weight:2,dashArray:'8 10'};
  }

  // Called by RN via injectJavaScript after GPS position is obtained
  window.setUserLocation = function(lat, lng){
    if(userMarker) map.removeLayer(userMarker);
    userMarker = L.marker([lat, lng], {
      icon: L.divIcon({
        html: '<div style="width:16px;height:16px;background:#2196F3;border:3px solid #fff;border-radius:50%;box-shadow:0 0 8px rgba(33,150,243,0.7)"></div>',
        iconSize:[16,16], iconAnchor:[8,8], className:''
      })
    }).addTo(map).bindPopup('Vous êtes ici');
    map.setView([lat, lng], 12);
  };

  // Called by RN to add a radius circle and auto-zoom to fit it
  window.addCircle = function(km, lat, lng){
    if(circles[km]) map.removeLayer(circles[km]);
    circles[km] = L.circle([lat, lng], circleOptions(km)).addTo(map);
    map.fitBounds(circles[km].getBounds(), {padding:[30,30], maxZoom:13});
  };

  // Called by RN to remove a radius circle
  window.removeCircle = function(km){
    if(circles[km]){ map.removeLayer(circles[km]); delete circles[km]; }
  };

  function openVia(slug, name){
    try{ window.ReactNativeWebView.postMessage(JSON.stringify({type:'via',slug:slug,name:name})); }catch(e){}
  }
})();
</script>
</body>
</html>`;
}

const styles = StyleSheet.create({
  container: {flex: 1},
  webview: {flex: 1},
  controls: {
    position: 'absolute',
    top: 56,
    right: 8,
    alignItems: 'flex-end',
  },
  locateBtn: {
    backgroundColor: '#2E7D32',
    borderRadius: 6,
    paddingVertical: 8,
    paddingHorizontal: 12,
    marginBottom: 5,
    minWidth: 130,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 4,
  },
  locateBtnText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 13,
  },
  kmBtn: {
    backgroundColor: '#fff',
    borderRadius: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    marginBottom: 5,
    minWidth: 80,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'rgba(0,0,0,0.2)',
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  kmBtnActive: {
    backgroundColor: '#2E7D32',
    borderColor: '#2E7D32',
  },
  kmBtnText: {
    color: '#333',
    fontSize: 13,
    fontWeight: '500',
  },
  kmBtnTextActive: {
    color: '#fff',
  },
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
