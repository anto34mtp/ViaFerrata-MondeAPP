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
import {getDashboard, DashboardData} from '../api/client';
import {useLang} from '../context/LangContext';
import {useAuth} from '../context/AuthContext';
import {formatDate} from '../utils/helpers';

const DashboardScreen: React.FC = () => {
  const {t} = useLang();
  const {user, token} = useAuth();
  const navigation = useNavigation<any>();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchDashboard = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }
    try {
      const res = await getDashboard();
      setData(res.data);
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token, t]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchDashboard();
  };

  if (!token) {
    return (
      <View style={styles.notLoggedIn}>
        <Text style={styles.notLoggedInEmoji}>🔐</Text>
        <Text style={styles.notLoggedInText}>{t.settings.notLoggedIn}</Text>
        <TouchableOpacity
          style={styles.loginBtn}
          onPress={() => navigation.navigate('Login')}>
          <Text style={styles.loginBtnText}>{t.common.login}</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.greeting}>
          {t.dashboard.hello}, {user?.username || ''} 👋
        </Text>
      </View>

      {/* Stats Cards */}
      {data?.stats && (
        <View style={styles.statsRow}>
          <StatCard
            icon="❤️"
            value={data.stats.favorites_count || 0}
            label={t.dashboard.totalFavorites}
          />
          <StatCard
            icon="📓"
            value={data.stats.logbook_count || 0}
            label={t.dashboard.totalLogbook}
          />
          <StatCard
            icon="🗺️"
            value={data.stats.trips_count || 0}
            label={t.dashboard.totalTrips}
          />
        </View>
      )}

      {/* Recent Favorites */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t.dashboard.recentFavorites}</Text>
          <TouchableOpacity onPress={() => navigation.navigate('Favorites')}>
            <Text style={styles.seeAll}>{t.common.seeAll}</Text>
          </TouchableOpacity>
        </View>
        {!data?.recent_favorites?.length ? (
          <Text style={styles.emptyText}>{t.dashboard.noFavorites}</Text>
        ) : (
          data.recent_favorites.slice(0, 3).map(fav => (
            <TouchableOpacity
              key={fav.id}
              style={styles.listItem}
              onPress={() =>
                fav.via &&
                navigation.navigate('ViaDetail', {
                  slug: fav.via.slug,
                  name: fav.via.name,
                })
              }>
              <Text style={styles.listItemEmoji}>
                {fav.status === 'done' ? '✅' : '📍'}
              </Text>
              <View style={styles.listItemContent}>
                <Text style={styles.listItemName}>{fav.via?.name || `Via #${fav.via_id}`}</Text>
                <Text style={styles.listItemSub}>
                  {fav.status === 'done' ? t.favorites.status.done : t.favorites.status.to_do}
                </Text>
              </View>
            </TouchableOpacity>
          ))
        )}
      </View>

      {/* Recent Logbook */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t.dashboard.recentLogbook}</Text>
          <TouchableOpacity onPress={() => navigation.navigate('Logbook')}>
            <Text style={styles.seeAll}>{t.common.seeAll}</Text>
          </TouchableOpacity>
        </View>
        {!data?.recent_logbook?.length ? (
          <Text style={styles.emptyText}>{t.dashboard.noLogbook}</Text>
        ) : (
          data.recent_logbook.slice(0, 3).map(entry => (
            <TouchableOpacity
              key={entry.id}
              style={styles.listItem}
              onPress={() =>
                entry.via &&
                navigation.navigate('ViaDetail', {
                  slug: entry.via.slug,
                  name: entry.via.name,
                })
              }>
              <Text style={styles.listItemEmoji}>📓</Text>
              <View style={styles.listItemContent}>
                <Text style={styles.listItemName}>
                  {entry.via?.name || `Via #${entry.via_id}`}
                </Text>
                <Text style={styles.listItemSub}>
                  {formatDate(entry.done_date)}
                </Text>
              </View>
            </TouchableOpacity>
          ))
        )}
      </View>

      {/* My Trips */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t.dashboard.myTrips}</Text>
          <TouchableOpacity onPress={() => navigation.navigate('RoadTrips')}>
            <Text style={styles.seeAll}>{t.common.seeAll}</Text>
          </TouchableOpacity>
        </View>
        {!data?.trips?.length ? (
          <Text style={styles.emptyText}>{t.dashboard.noTrips}</Text>
        ) : (
          data.trips.slice(0, 3).map(trip => (
            <TouchableOpacity
              key={trip.id}
              style={styles.listItem}
              onPress={() =>
                navigation.navigate('RoadTripDetail', {
                  id: trip.id,
                  name: trip.name,
                })
              }>
              <Text style={styles.listItemEmoji}>🗺️</Text>
              <View style={styles.listItemContent}>
                <Text style={styles.listItemName}>{trip.name}</Text>
                {trip.start_date ? (
                  <Text style={styles.listItemSub}>
                    {formatDate(trip.start_date)}
                    {trip.nb_days ? ` · ${trip.nb_days}j` : ''}
                  </Text>
                ) : null}
              </View>
            </TouchableOpacity>
          ))
        )}
      </View>

      <View style={{height: 24}} />
    </ScrollView>
  );
};

const StatCard: React.FC<{icon: string; value: number; label: string}> = ({
  icon,
  value,
  label,
}) => (
  <View style={styles.statCard}>
    <Text style={styles.statIcon}>{icon}</Text>
    <Text style={styles.statValue}>{value}</Text>
    <Text style={styles.statLabel}>{label}</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#F5F5F5'},
  notLoggedIn: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 32,
  },
  notLoggedInEmoji: {fontSize: 56, marginBottom: 16},
  notLoggedInText: {fontSize: 16, color: '#666', marginBottom: 20},
  loginBtn: {
    backgroundColor: '#2E7D32',
    borderRadius: 10,
    paddingHorizontal: 32,
    paddingVertical: 12,
  },
  loginBtnText: {color: '#FFF', fontWeight: '700', fontSize: 16},
  header: {
    backgroundColor: '#2E7D32',
    padding: 20,
    paddingTop: 24,
    paddingBottom: 24,
  },
  greeting: {
    fontSize: 22,
    fontWeight: '800',
    color: '#FFFFFF',
  },
  statsRow: {
    flexDirection: 'row',
    marginHorizontal: 12,
    marginTop: -16,
    marginBottom: 8,
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07,
    shadowRadius: 4,
    elevation: 3,
    padding: 16,
  },
  statCard: {
    flex: 1,
    alignItems: 'center',
  },
  statIcon: {fontSize: 22, marginBottom: 4},
  statValue: {fontSize: 22, fontWeight: '800', color: '#2E7D32'},
  statLabel: {fontSize: 11, color: '#666', textAlign: 'center'},
  section: {
    marginTop: 16,
    marginHorizontal: 12,
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.06,
    shadowRadius: 3,
    elevation: 2,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sectionTitle: {fontSize: 16, fontWeight: '700', color: '#1A1A1A'},
  seeAll: {fontSize: 13, color: '#2E7D32', fontWeight: '600'},
  emptyText: {color: '#999', fontSize: 14, fontStyle: 'italic', paddingVertical: 4},
  listItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: '#F0F0F0',
  },
  listItemEmoji: {fontSize: 22, marginRight: 12},
  listItemContent: {flex: 1},
  listItemName: {fontSize: 14, fontWeight: '600', color: '#222'},
  listItemSub: {fontSize: 12, color: '#888', marginTop: 2},
});

export default DashboardScreen;
