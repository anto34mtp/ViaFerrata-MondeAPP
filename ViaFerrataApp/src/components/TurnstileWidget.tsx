import React, {useRef, useState} from 'react';
import {View, ActivityIndicator, StyleSheet, Text} from 'react-native';
import {WebView} from 'react-native-webview';
import {TURNSTILE_SITE_KEY} from '../config';

interface Props {
  onVerify: (token: string) => void;
}

function buildHtml(siteKey: string): string {
  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"/>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:70px;overflow:hidden}
</style>
</head>
<body>
<div class="cf-turnstile"
  data-sitekey="${siteKey}"
  data-callback="onVerify"
  data-theme="light"
  data-size="normal"></div>
<script>
function onVerify(token) {
  window.ReactNativeWebView.postMessage(JSON.stringify({type:'turnstile',token:token}));
}
</script>
</body>
</html>`;
}

export default function TurnstileWidget({onVerify}: Props) {
  const [ready, setReady] = useState(false);
  const webRef = useRef<WebView>(null);

  return (
    <View style={styles.container}>
      {!ready && (
        <View style={styles.placeholder}>
          <ActivityIndicator color="#2E7D32" />
          <Text style={styles.placeholderText}>Chargement de la vérification…</Text>
        </View>
      )}
      <WebView
        ref={webRef}
        source={{html: buildHtml(TURNSTILE_SITE_KEY), baseUrl: 'https://viaferrata-monde.fr'}}
        style={[styles.webview, !ready && styles.hidden]}
        javaScriptEnabled
        originWhitelist={['*']}
        mixedContentMode="always"
        onLoad={() => setReady(true)}
        onMessage={e => {
          try {
            const msg = JSON.parse(e.nativeEvent.data);
            if (msg.type === 'turnstile' && msg.token) {
              onVerify(msg.token);
            }
          } catch {}
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {height: 80, marginVertical: 8},
  webview: {height: 80, backgroundColor: 'transparent'},
  hidden: {position: 'absolute', opacity: 0, width: 0, height: 0},
  placeholder: {
    height: 80,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f0f0f0',
    borderRadius: 8,
    flexDirection: 'row',
  },
  placeholderText: {fontSize: 13, color: '#666'},
});
