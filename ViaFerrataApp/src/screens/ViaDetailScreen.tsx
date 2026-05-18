import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Alert,
  Image,
  FlatList,
  Dimensions,
  ActivityIndicator,
  Linking,
  Platform,
} from 'react-native';
import {useRoute, useNavigation} from '@react-navigation/native';
import {WebView} from 'react-native-webview';
import {getViaDetail, rateVia, commentVia, addFavorite, deleteFavorite, getFavorites, Via} from '../api/client';
import {useLang} from '../context/LangContext';
import {useAuth} from '../context/AuthContext';
import DifficultyBadge from '../components/DifficultyBadge';
import StatusBadge from '../components/StatusBadge';
import RatingBar from '../components/RatingBar';
import {formatDuration, formatGPS} from '../utils/helpers';

const {width} = Dimensions.get('window');

function openNavigation(lat: number, lng: number, name: string) {
  const label = encodeURIComponent(name);
  const url = Platform.OS === 'ios'
    ? `maps://?daddr=${lat},${lng}&q=${label}`
    : `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  Linking.openURL(url).catch(() =>
    Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`),
  );
}

function buildHtmlContent(html: string): string {
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"/>
<style>
  body{margin:0;padding:0;font-family:-apple-system,Roboto,sans-serif;font-size:14px;color:#444;line-height:1.7;overflow:hidden}
  h1,h2,h3{color:#2E7D32;margin:14px 0 6px;font-size:16px}
  h4,h5,h6{color:#333;margin:10px 0 4px;font-size:14px}
  p{margin:0 0 10px}
  ul,ol{margin:0 0 10px;padding-left:20px}
  li{margin-bottom:4px}
  strong,b{color:#333}
  a{color:#2E7D32}
  img{max-width:100%;height:auto}
  hr{border:none;border-top:1px solid #eee;margin:12px 0}
</style>
</head><body>${html}</body></html>`;
}

function buildMiniMapHTML(lat: number, lng: number, name: string): string {
  const safeName = name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>*{margin:0;padding:0}html,body,#map{width:100%;height:200px}</style>
</head><body>
<div id="map"></div>
<script>
var map=L.map('map',{zoomControl:true,dragging:true,scrollWheelZoom:false}).setView([${lat},${lng}],13);
L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{subdomains:'abcd',maxZoom:19}).addTo(map);
L.marker([${lat},${lng}]).addTo(map).bindPopup('${safeName}').openPopup();
</script>
</body></html>`;
}

const ViaDetailScreen: React.FC = () => {
  const {t} = useLang();
  const {token} = useAuth();
  const route = useRoute<any>();
  const navigation = useNavigation<any>();
  const {slug} = route.params;

  const [via, setVia] = useState<Via | null>(null);
  const [loading, setLoading] = useState(true);
  const [isFavorite, setIsFavorite] = useState(false);
  const [favoriteStatus, setFavoriteStatus] = useState<'to_do' | 'done'>('to_do');

  // Rating state
  const [ratingGeneral, setRatingGeneral] = useState(0);
  const [ratingBeauty, setRatingBeauty] = useState(0);
  const [ratingDifficulty, setRatingDifficulty] = useState(0);
  const [submittingRating, setSubmittingRating] = useState(false);

  // Comment state
  const [commentText, setCommentText] = useState('');
  const [authorName, setAuthorName] = useState('');
  const [submittingComment, setSubmittingComment] = useState(false);

  // Photos state
  const [photoIndex, setPhotoIndex] = useState(0);

  // Dynamic heights for HTML WebViews
  const [descHeight, setDescHeight] = useState(120);
  const [pricingHeight, setPricingHeight] = useState(80);

  const fetchVia = useCallback(async () => {
    try {
      const res = await getViaDetail(slug);
      setVia(res.data);
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    } finally {
      setLoading(false);
    }
  }, [slug, t]);

  const checkFavorite = useCallback(async () => {
    if (!token || !via) return;
    try {
      const res = await getFavorites();
      const fav = res.data.find(f => f.via_id === via.id);
      if (fav) {
        setIsFavorite(true);
        setFavoriteStatus(fav.status);
      }
    } catch {
      // silent
    }
  }, [token, via]);

  useEffect(() => {
    fetchVia();
  }, [fetchVia]);

  useEffect(() => {
    checkFavorite();
  }, [checkFavorite]);

  const handleToggleFavorite = async () => {
    if (!token) {
      navigation.navigate('Login');
      return;
    }
    if (!via) return;
    try {
      if (isFavorite) {
        await deleteFavorite(via.id);
        setIsFavorite(false);
      } else {
        await addFavorite({via_id: via.id, status: 'to_do'});
        setIsFavorite(true);
        setFavoriteStatus('to_do');
      }
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    }
  };

  const handleSubmitRating = async () => {
    if (!token) {
      navigation.navigate('Login');
      return;
    }
    if (!via || ratingGeneral === 0) return;
    setSubmittingRating(true);
    try {
      await rateVia(slug, {
        rating_general: ratingGeneral,
        rating_beauty: ratingBeauty,
        rating_difficulty: ratingDifficulty,
      });
      Alert.alert('', 'Merci pour votre évaluation !');
      setRatingGeneral(0);
      setRatingBeauty(0);
      setRatingDifficulty(0);
      fetchVia();
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    } finally {
      setSubmittingRating(false);
    }
  };

  const handleSubmitComment = async () => {
    if (!commentText.trim()) return;
    setSubmittingComment(true);
    try {
      await commentVia(slug, {
        content: commentText.trim(),
        author_name: authorName.trim() || undefined,
      });
      setCommentText('');
      setAuthorName('');
      fetchVia();
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    } finally {
      setSubmittingComment(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2E7D32" />
      </View>
    );
  }

  if (!via) {
    return (
      <View style={styles.loadingContainer}>
        <Text>{t.common.error}</Text>
      </View>
    );
  }

  const hasLocation = via.gps_lat && via.gps_lng;
  const photos = via.photos || [];
  const comments = via.comments || [];

  return (
    <ScrollView style={styles.container}>
      {/* Photos / image de couverture */}
      {photos.length > 0 ? (
        <View>
          <FlatList
            data={photos}
            horizontal
            pagingEnabled
            showsHorizontalScrollIndicator={false}
            keyExtractor={item => String(item.id)}
            onMomentumScrollEnd={e => {
              const idx = Math.round(e.nativeEvent.contentOffset.x / width);
              setPhotoIndex(idx);
            }}
            renderItem={({item}) => (
              <Image
                source={{uri: item.url}}
                style={{width, height: 240}}
                resizeMode="cover"
              />
            )}
          />
          {photos.length > 1 && (
            <View style={styles.photoDots}>
              {photos.map((_, i) => (
                <View
                  key={i}
                  style={[styles.dot, i === photoIndex && styles.dotActive]}
                />
              ))}
            </View>
          )}
        </View>
      ) : via.image_url ? (
        <Image
          source={{uri: via.image_url}}
          style={{width, height: 240}}
          resizeMode="cover"
        />
      ) : (
        <View style={styles.noPhoto}>
          <Text style={styles.noPhotoText}>🏔️</Text>
        </View>
      )}

      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <Text style={styles.name}>{via.name}</Text>
          <TouchableOpacity onPress={handleToggleFavorite}>
            <Text style={styles.favoriteIcon}>{isFavorite ? '❤️' : '🤍'}</Text>
          </TouchableOpacity>
        </View>
        <Text style={styles.location}>
          {[via.location, via.department_name, via.country]
            .filter(Boolean)
            .join(' · ')}
        </Text>
        <View style={styles.badges}>
          <DifficultyBadge level={via.difficulty} />
          <StatusBadge status={via.opening_status} />
        </View>
      </View>

      {/* Info Grid */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Informations</Text>
        <View style={styles.infoGrid}>
          {via.duration_min || via.duration_max ? (
            <InfoRow
              label={t.viaDetail.duration}
              value={formatDuration(via.duration_min, via.duration_max)}
            />
          ) : null}
          {via.length_m ? (
            <InfoRow label={t.viaDetail.length} value={`${via.length_m} m`} />
          ) : null}
          {via.elevation_m ? (
            <InfoRow label={t.viaDetail.elevation} value={`${via.elevation_m} m`} />
          ) : null}
          {via.altitude_max_m ? (
            <InfoRow label={t.viaDetail.altitude} value={`${via.altitude_max_m} m`} />
          ) : null}
          {via.country ? (
            <InfoRow label={t.viaDetail.country} value={via.country} />
          ) : null}
          {via.department_name ? (
            <InfoRow label={t.viaDetail.department} value={via.department_name} />
          ) : null}
          {hasLocation ? (
            <InfoRow
              label={t.viaDetail.gps}
              value={formatGPS(via.gps_lat, via.gps_lng)}
            />
          ) : null}
        </View>
      </View>

      {/* Description — rendered as HTML to preserve sections and formatting */}
      {via.description ? (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>{t.viaDetail.description}</Text>
          <WebView
            source={{html: buildHtmlContent(via.description)}}
            style={{height: descHeight}}
            scrollEnabled={false}
            showsVerticalScrollIndicator={false}
            originWhitelist={['*']}
            mixedContentMode="always"
            injectedJavaScript="setTimeout(()=>window.ReactNativeWebView.postMessage(String(document.body.scrollHeight)),300)"
            onMessage={e => setDescHeight(Math.max(60, Number(e.nativeEvent.data) + 8))}
          />
        </View>
      ) : null}

      {/* Pricing & Tourism */}
      {(via.pricing_info || via.tourism_office) ? (
        <View style={styles.card}>
          {via.pricing_info ? (
            <>
              <Text style={styles.cardTitle}>{t.viaDetail.pricing}</Text>
              <WebView
                source={{html: buildHtmlContent(via.pricing_info)}}
                style={{height: pricingHeight}}
                scrollEnabled={false}
                showsVerticalScrollIndicator={false}
                originWhitelist={['*']}
                mixedContentMode="always"
                injectedJavaScript="setTimeout(()=>window.ReactNativeWebView.postMessage(String(document.body.scrollHeight)),300)"
                onMessage={e => setPricingHeight(Math.max(40, Number(e.nativeEvent.data) + 8))}
              />
            </>
          ) : null}
          {via.tourism_office ? (
            <>
              <Text style={[styles.cardTitle, {marginTop: via.pricing_info ? 12 : 0}]}>
                {t.viaDetail.tourism}
              </Text>
              <Text style={styles.description}>{via.tourism_office}</Text>
            </>
          ) : null}
        </View>
      ) : null}

      {/* Map + M'y rendre */}
      {hasLocation ? (
        <View style={styles.card}>
          <View style={styles.cardTitleRow}>
            <Text style={styles.cardTitle}>{t.viaDetail.location}</Text>
            <TouchableOpacity
              style={styles.navBtn}
              onPress={() => openNavigation(via.gps_lat!, via.gps_lng!, via.name)}>
              <Text style={styles.navBtnText}>🧭 M'y rendre</Text>
            </TouchableOpacity>
          </View>
          <WebView
            source={{html: buildMiniMapHTML(via.gps_lat!, via.gps_lng!, via.name)}}
            style={styles.map}
            javaScriptEnabled
            scrollEnabled={false}
            originWhitelist={['*']}
          />
        </View>
      ) : null}

      {/* Ratings */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t.viaDetail.ratings}</Text>
        {via.ratings_count && via.ratings_count > 0 ? (
          <>
            <RatingBar
              label={t.viaDetail.ratingGeneral}
              value={via.avg_rating_general || 0}
              readonly
            />
            <RatingBar
              label={t.viaDetail.ratingBeauty}
              value={via.avg_rating_beauty || 0}
              readonly
            />
            <RatingBar
              label={t.viaDetail.ratingDifficulty}
              value={via.avg_rating_difficulty || 0}
              readonly
            />
            <Text style={styles.ratingCount}>
              {via.ratings_count} avis
            </Text>
          </>
        ) : (
          <Text style={styles.emptyText}>{t.viaDetail.noRatings}</Text>
        )}

        {/* Submit Rating */}
        <View style={styles.submitRatingSection}>
          <Text style={styles.subTitle}>{t.viaDetail.yourRating}</Text>
          <RatingBar
            label={t.viaDetail.ratingGeneral}
            value={ratingGeneral}
            onValueChange={setRatingGeneral}
          />
          <RatingBar
            label={t.viaDetail.ratingBeauty}
            value={ratingBeauty}
            onValueChange={setRatingBeauty}
          />
          <RatingBar
            label={t.viaDetail.ratingDifficulty}
            value={ratingDifficulty}
            onValueChange={setRatingDifficulty}
          />
          <TouchableOpacity
            style={[styles.submitBtn, ratingGeneral === 0 && styles.submitBtnDisabled]}
            onPress={handleSubmitRating}
            disabled={submittingRating || ratingGeneral === 0}>
            {submittingRating ? (
              <ActivityIndicator color="#FFF" size="small" />
            ) : (
              <Text style={styles.submitBtnText}>{t.viaDetail.submitRating}</Text>
            )}
          </TouchableOpacity>
        </View>
      </View>

      {/* Comments */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t.viaDetail.comments}</Text>
        {comments.length === 0 ? (
          <Text style={styles.emptyText}>{t.viaDetail.noComments}</Text>
        ) : (
          comments.map(c => (
            <View key={c.id} style={styles.commentItem}>
              <View style={styles.commentHeader}>
                <Text style={styles.commentAuthor}>
                  {c.author_name || 'Anonyme'}
                </Text>
                <Text style={styles.commentDate}>
                  {new Date(c.created_at).toLocaleDateString()}
                </Text>
              </View>
              <Text style={styles.commentContent}>{c.content}</Text>
            </View>
          ))
        )}

        {/* Add Comment */}
        <View style={styles.addCommentSection}>
          <Text style={styles.subTitle}>{t.viaDetail.addComment}</Text>
          <TextInput
            style={styles.input}
            placeholder={t.viaDetail.authorName}
            value={authorName}
            onChangeText={setAuthorName}
          />
          <TextInput
            style={[styles.input, styles.textArea]}
            placeholder={t.viaDetail.commentPlaceholder}
            value={commentText}
            onChangeText={setCommentText}
            multiline
            numberOfLines={3}
            textAlignVertical="top"
          />
          <TouchableOpacity
            style={[styles.submitBtn, !commentText.trim() && styles.submitBtnDisabled]}
            onPress={handleSubmitComment}
            disabled={submittingComment || !commentText.trim()}>
            {submittingComment ? (
              <ActivityIndicator color="#FFF" size="small" />
            ) : (
              <Text style={styles.submitBtnText}>{t.common.send}</Text>
            )}
          </TouchableOpacity>
        </View>
      </View>

      <View style={{height: 32}} />
    </ScrollView>
  );
};

const InfoRow: React.FC<{label: string; value: string}> = ({label, value}) => (
  <View style={styles.infoRow}>
    <Text style={styles.infoLabel}>{label}</Text>
    <Text style={styles.infoValue}>{value}</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#F5F5F5'},
  loadingContainer: {flex: 1, justifyContent: 'center', alignItems: 'center'},
  noPhoto: {
    height: 200,
    backgroundColor: '#E8F5E9',
    justifyContent: 'center',
    alignItems: 'center',
  },
  noPhotoText: {fontSize: 60},
  photoDots: {
    position: 'absolute',
    bottom: 12,
    left: 0,
    right: 0,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 6,
  },
  dot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: 'rgba(255,255,255,0.5)',
  },
  dotActive: {
    backgroundColor: '#FFFFFF',
  },
  header: {
    backgroundColor: '#FFFFFF',
    padding: 16,
    marginBottom: 8,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  name: {
    flex: 1,
    fontSize: 22,
    fontWeight: '800',
    color: '#1A1A1A',
    marginRight: 8,
  },
  favoriteIcon: {
    fontSize: 26,
  },
  location: {
    fontSize: 14,
    color: '#666',
    marginTop: 4,
    marginBottom: 10,
  },
  badges: {
    flexDirection: 'row',
    gap: 8,
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 12,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.06,
    shadowRadius: 3,
    elevation: 2,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1A1A1A',
    marginBottom: 12,
  },
  cardTitleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  navBtn: {
    backgroundColor: '#1565C0',
    borderRadius: 8,
    paddingVertical: 6,
    paddingHorizontal: 12,
  },
  navBtnText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '600',
  },
  infoGrid: {
    gap: 4,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#F0F0F0',
  },
  infoLabel: {
    fontSize: 14,
    color: '#666',
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
  },
  description: {
    fontSize: 14,
    color: '#444',
    lineHeight: 22,
  },
  map: {
    height: 200,
    borderRadius: 8,
    overflow: 'hidden',
  },
  ratingCount: {
    fontSize: 12,
    color: '#999',
    marginTop: 8,
    textAlign: 'right',
  },
  emptyText: {
    color: '#999',
    fontSize: 14,
    fontStyle: 'italic',
  },
  submitRatingSection: {
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: '#F0F0F0',
  },
  subTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#444',
    marginBottom: 10,
  },
  submitBtn: {
    backgroundColor: '#2E7D32',
    borderRadius: 8,
    padding: 12,
    alignItems: 'center',
    marginTop: 12,
  },
  submitBtnDisabled: {
    backgroundColor: '#A5D6A7',
  },
  submitBtnText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 15,
  },
  commentItem: {
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F0F0F0',
  },
  commentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  commentAuthor: {
    fontSize: 13,
    fontWeight: '700',
    color: '#2E7D32',
  },
  commentDate: {
    fontSize: 12,
    color: '#999',
  },
  commentContent: {
    fontSize: 14,
    color: '#444',
    lineHeight: 20,
  },
  addCommentSection: {
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: '#F0F0F0',
  },
  input: {
    borderWidth: 1,
    borderColor: '#E0E0E0',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    marginBottom: 10,
    backgroundColor: '#FAFAFA',
    color: '#1A1A1A',
  },
  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
});

export default ViaDetailScreen;
