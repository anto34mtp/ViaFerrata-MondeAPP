import React, {useEffect, useState, useCallback} from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  Modal,
} from 'react-native';
import MapView, {Marker, Callout} from 'react-native-maps';
import {useNavigation} from '@react-navigation/native';
import {getMapVias, MapPoint} from '../api/client';
import {useLang} from '../context/LangContext';
import DifficultyBadge from '../components/DifficultyBadge';

const MapScreen: React.FC = () => {
  const {t} = useLang();
  const navigation = useNavigation<any>();
  const [points, setPoints] = useState<MapPoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<MapPoint | null>(null);

  const fetchPoints = useCallback(async () => {
    try {
      const res = await getMapVias();
      const data = Array.isArray(res.data) ? res.data : [];
      setPoints(data.filter(p => p.gps_lat && p.gps_lng));
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchPoints();
  }, [fetchPoints]);

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2E7D32" />
        <Text style={styles.loadingText}>{t.map.loading}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <MapView
        style={styles.map}
        initialRegion={{
          latitude: 45.8,
          longitude: 6.5,
          latitudeDelta: 20,
          longitudeDelta: 20,
        }}>
        {points.map(point => (
          <Marker
            key={point.id}
            coordinate={{
              latitude: point.gps_lat,
              longitude: point.gps_lng,
            }}
            title={point.name}
            onPress={() => setSelected(point)}
            pinColor="#2E7D32"
          />
        ))}
      </MapView>

      <View style={styles.countBadge}>
        <Text style={styles.countText}>{points.length} vias</Text>
      </View>

      {/* Selected via popup */}
      {selected && (
        <View style={styles.popup}>
          <TouchableOpacity
            style={styles.popupClose}
            onPress={() => setSelected(null)}>
            <Text style={styles.popupCloseText}>✕</Text>
          </TouchableOpacity>
          <Text style={styles.popupName}>{selected.name}</Text>
          <View style={styles.popupRow}>
            {selected.difficulty ? (
              <DifficultyBadge level={selected.difficulty} size="small" />
            ) : null}
            {selected.country ? (
              <Text style={styles.popupCountry}>{selected.country}</Text>
            ) : null}
          </View>
          <TouchableOpacity
            style={styles.popupBtn}
            onPress={() => {
              setSelected(null);
              navigation.navigate('ViaDetail', {
                slug: selected.slug,
                name: selected.name,
              });
            }}>
            <Text style={styles.popupBtnText}>{t.map.viewVia} →</Text>
          </TouchableOpacity>
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
  },
  loadingText: {
    marginTop: 12,
    color: '#666',
    fontSize: 15,
  },
  map: {
    flex: 1,
  },
  countBadge: {
    position: 'absolute',
    top: 16,
    right: 16,
    backgroundColor: '#2E7D32',
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  countText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 13,
  },
  popup: {
    position: 'absolute',
    bottom: 24,
    left: 16,
    right: 16,
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 8,
  },
  popupClose: {
    position: 'absolute',
    top: 12,
    right: 12,
    padding: 4,
  },
  popupCloseText: {
    fontSize: 16,
    color: '#999',
  },
  popupName: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1A1A1A',
    marginBottom: 8,
    marginRight: 24,
  },
  popupRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
  },
  popupCountry: {
    fontSize: 13,
    color: '#666',
  },
  popupBtn: {
    backgroundColor: '#2E7D32',
    borderRadius: 8,
    padding: 10,
    alignItems: 'center',
  },
  popupBtnText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 14,
  },
});

export default MapScreen;
