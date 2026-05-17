import React, {useRef, useState} from 'react';
import {
  View,
  ActivityIndicator,
  Text,
  StyleSheet,
  Platform,
} from 'react-native';
import {WebView, WebViewNavigation} from 'react-native-webview';
import {useNavigation} from '@react-navigation/native';
import {useAuth} from '../context/AuthContext';
import {useLang} from '../context/LangContext';

const BASE_URL = 'https://viaferrata-monde.fr';

const MapScreen: React.FC = () => {
  const {t} = useLang();
  const {token} = useAuth();
  const navigation = useNavigation<any>();
  const webRef = useRef<WebView>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  // Build map URL — pass JWT token if logged in so the map can call auth'd endpoints
  const mapUrl =
    BASE_URL +
    '/mobile-map' +
    (token ? '?token=' + encodeURIComponent(token) : '');

  // Intercept viaferrata://via/{slug} deep-links posted by the Leaflet popup
  const handleNavChange = (state: WebViewNavigation): boolean => {
    const url = state.url ?? '';
    if (url.startsWith('viaferrata://via/')) {
      const slug = url.replace('viaferrata://via/', '').split('?')[0];
      navigation.navigate('ViaDetail', {slug, name: slug});
      return false; // block the WebView from navigating
    }
    // Allow http(s) navigation (tile loading, API calls)
    return true;
  };

  // Also handle postMessage from the page as an alternative
  const handleMessage = (event: {nativeEvent: {data: string}}) => {
    try {
      const msg = JSON.parse(event.nativeEvent.data);
      if (msg.type === 'via' && msg.slug) {
        navigation.navigate('ViaDetail', {slug: msg.slug, name: msg.name ?? msg.slug});
      }
    } catch {}
  };

  if (error) {
    return (
      <View style={styles.centered}>
        <Text style={styles.errorEmoji}>🌐</Text>
        <Text style={styles.errorText}>
          Impossible de charger la carte.{'\n'}Vérifiez votre connexion.
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <WebView
        ref={webRef}
        source={{uri: mapUrl}}
        style={styles.webview}
        javaScriptEnabled
        domStorageEnabled
        originWhitelist={['https://*', 'viaferrata://*']}
        // Intercept deep-link navigation
        onShouldStartLoadWithRequest={handleNavChange}
        onMessage={handleMessage}
        onLoadEnd={() => setLoading(false)}
        onError={() => {
          setLoading(false);
          setError(true);
        }}
        // Fix Android mixed-content (tile CDN over http)
        mixedContentMode={Platform.OS === 'android' ? 'always' : undefined}
      />
      {loading && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color="#2E7D32" />
          <Text style={styles.overlayText}>{t.map?.loading ?? 'Chargement…'}</Text>
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  webview: {flex: 1},
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#F5F5F5',
    justifyContent: 'center',
    alignItems: 'center',
  },
  overlayText: {marginTop: 12, fontSize: 15, color: '#666'},
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 32,
  },
  errorEmoji: {fontSize: 48, marginBottom: 16},
  errorText: {fontSize: 15, color: '#666', textAlign: 'center', lineHeight: 22},
});

export default MapScreen;
