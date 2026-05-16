import React, {useEffect, useState, useCallback} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
  Alert,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {getTopRatedVias, getStats, getVias, Via, Stats} from '../api/client';
import {useLang} from '../context/LangContext';
import ViaCard from '../components/ViaCard';
import LoadingScreen from '../components/LoadingScreen';

const HomeScreen: React.FC = () => {
  const {t} = useLang();
  const navigation = useNavigation<any>();
  const [topRated, setTopRated] = useState<Via[]>([]);
  const [latest, setLatest] = useState<Via[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = useCallback(async () => {
    try {
      const [topRes, latestRes, statsRes] = await Promise.all([
        getTopRatedVias(6),
        getVias({page: 1, limit: 6, order_by: 'recent'}),
        getStats(),
      ]);
      setTopRated(topRes.data || []);
      setLatest(
        Array.isArray(latestRes.data)
          ? latestRes.data
          : latestRes.data?.data || [],
      );
      setStats(statsRes.data);
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [t]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  if (loading) return <LoadingScreen message={t.common.loading} />;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Hero */}
      <View style={styles.hero}>
        <Text style={styles.heroTitle}>{t.home.title}</Text>
        <Text style={styles.heroSubtitle}>{t.home.subtitle}</Text>
      </View>

      {/* Stats */}
      {stats && (
        <View style={styles.statsRow}>
          <View style={styles.statCard}>
            <Text style={styles.statNumber}>{stats.total_vias}</Text>
            <Text style={styles.statLabel}>{t.home.totalVias}</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statCard}>
            <Text style={styles.statNumber}>{stats.countries}</Text>
            <Text style={styles.statLabel}>{t.home.countries}</Text>
          </View>
        </View>
      )}

      {/* Top Rated */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t.home.topRated}</Text>
          <TouchableOpacity
            onPress={() =>
              navigation.navigate('Catalog', {order_by: 'rating'})
            }>
            <Text style={styles.seeAll}>{t.common.seeAll}</Text>
          </TouchableOpacity>
        </View>
        {topRated.length === 0 ? (
          <Text style={styles.empty}>{t.common.noResults}</Text>
        ) : (
          topRated.map(via => (
            <ViaCard
              key={via.id}
              via={via}
              onPress={() =>
                navigation.navigate('ViaDetail', {slug: via.slug, name: via.name})
              }
            />
          ))
        )}
      </View>

      {/* Latest Vias */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t.home.latestVias}</Text>
          <TouchableOpacity onPress={() => navigation.navigate('Catalog')}>
            <Text style={styles.seeAll}>{t.common.seeAll}</Text>
          </TouchableOpacity>
        </View>
        {latest.length === 0 ? (
          <Text style={styles.empty}>{t.common.noResults}</Text>
        ) : (
          latest.map(via => (
            <ViaCard
              key={via.id}
              via={via}
              onPress={() =>
                navigation.navigate('ViaDetail', {slug: via.slug, name: via.name})
              }
            />
          ))
        )}
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  hero: {
    backgroundColor: '#2E7D32',
    padding: 24,
    paddingTop: 32,
    paddingBottom: 28,
  },
  heroTitle: {
    fontSize: 26,
    fontWeight: '800',
    color: '#FFFFFF',
    marginBottom: 6,
  },
  heroSubtitle: {
    fontSize: 14,
    color: '#C8E6C9',
    lineHeight: 20,
  },
  statsRow: {
    flexDirection: 'row',
    backgroundColor: '#FFFFFF',
    marginHorizontal: 16,
    marginTop: -16,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 3,
    padding: 16,
  },
  statCard: {
    flex: 1,
    alignItems: 'center',
  },
  statDivider: {
    width: 1,
    backgroundColor: '#E0E0E0',
  },
  statNumber: {
    fontSize: 28,
    fontWeight: '800',
    color: '#2E7D32',
  },
  statLabel: {
    fontSize: 12,
    color: '#666',
    marginTop: 2,
  },
  section: {
    marginTop: 24,
    marginBottom: 8,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    marginBottom: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1A1A1A',
  },
  seeAll: {
    fontSize: 14,
    color: '#2E7D32',
    fontWeight: '600',
  },
  empty: {
    textAlign: 'center',
    color: '#999',
    padding: 16,
  },
});

export default HomeScreen;
