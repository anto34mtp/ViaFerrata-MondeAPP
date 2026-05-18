import React, {useEffect, useState, useCallback} from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  Alert,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {
  getFavorites,
  deleteFavorite,
  addFavorite,
  Favorite,
} from '../api/client';
import {useLang} from '../context/LangContext';
import {useAuth} from '../context/AuthContext';
import DifficultyBadge from '../components/DifficultyBadge';
import {formatDate} from '../utils/helpers';

type Filter = 'all' | 'to_do' | 'done';

const FavoritesScreen: React.FC = () => {
  const {t} = useLang();
  const {token} = useAuth();
  const navigation = useNavigation<any>();
  const [favorites, setFavorites] = useState<Favorite[]>([]);
  const [filter, setFilter] = useState<Filter>('all');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchFavorites = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }
    try {
      const res = await getFavorites(filter === 'all' ? undefined : filter);
      setFavorites(Array.isArray(res.data) ? res.data : []);
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token, filter, t]);

  useEffect(() => {
    setLoading(true);
    fetchFavorites();
  }, [fetchFavorites]);

  const handleRemove = async (via_id: number) => {
    Alert.alert(t.favorites.remove, '', [
      {text: t.common.cancel, style: 'cancel'},
      {
        text: t.common.delete,
        style: 'destructive',
        onPress: async () => {
          try {
            await deleteFavorite(via_id);
            setFavorites(prev => prev.filter(f => f.via_id !== via_id));
          } catch (e) {
            Alert.alert(t.common.error, String(e));
          }
        },
      },
    ]);
  };

  const handleToggleStatus = async (fav: Favorite) => {
    const newStatus = fav.status === 'to_do' ? 'done' : 'to_do';
    try {
      await deleteFavorite(fav.via_id);
      await addFavorite({via_id: fav.via_id, status: newStatus});
      setFavorites(prev =>
        prev.map(f =>
          f.via_id === fav.via_id ? {...f, status: newStatus} : f,
        ),
      );
    } catch (e) {
      Alert.alert(t.common.error, String(e));
    }
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

  const renderItem = ({item}: {item: Favorite}) => (
    <View style={styles.item}>
      <TouchableOpacity
        style={styles.itemMain}
        onPress={() =>
          item.via &&
          navigation.navigate('ViaDetail', {
            slug: item.via.slug,
            name: item.via.name,
          })
        }>
        <View style={styles.itemHeader}>
          <Text style={styles.itemName} numberOfLines={2}>
            {item.via?.name || `Via #${item.via_id}`}
          </Text>
          <DifficultyBadge level={item.via?.difficulty} size="small" />
        </View>
        <Text style={styles.itemLocation} numberOfLines={1}>
          {[item.via?.location, item.via?.country].filter(Boolean).join(' · ')}
        </Text>
        <Text style={styles.itemDate}>
          {t.favorites.addedOn}: {formatDate(item.created_at)}
        </Text>
      </TouchableOpacity>

      <View style={styles.itemActions}>
        <TouchableOpacity
          style={[
            styles.statusBtn,
            item.status === 'done' ? styles.statusBtnDone : styles.statusBtnTodo,
          ]}
          onPress={() => handleToggleStatus(item)}>
          <Text style={styles.statusBtnText}>
            {item.status === 'done' ? '✓ ' + t.favorites.status.done : '○ ' + t.favorites.status.to_do}
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={styles.removeBtn}
          onPress={() => handleRemove(item.via_id)}>
          <Text style={styles.removeBtnText}>🗑️</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      {/* Filter tabs */}
      <View style={styles.filterRow}>
        {(['all', 'to_do', 'done'] as Filter[]).map(f => (
          <TouchableOpacity
            key={f}
            style={[styles.filterTab, filter === f && styles.filterTabActive]}
            onPress={() => setFilter(f)}>
            <Text
              style={[
                styles.filterTabText,
                filter === f && styles.filterTabTextActive,
              ]}>
              {f === 'all'
                ? t.favorites.all
                : f === 'to_do'
                ? t.favorites.toDo
                : t.favorites.done}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {loading ? (
        <ActivityIndicator style={{margin: 32}} color="#2E7D32" />
      ) : (
        <FlatList
          data={favorites}
          keyExtractor={item => String(item.id)}
          renderItem={renderItem}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => {setRefreshing(true); fetchFavorites();}} />
          }
          ListEmptyComponent={
            <Text style={styles.empty}>{t.favorites.noFavorites}</Text>
          }
          contentContainerStyle={{paddingBottom: 16}}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#F5F5F5'},
  notLoggedIn: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  notLoggedInEmoji: {fontSize: 56, marginBottom: 16},
  notLoggedInText: {fontSize: 16, color: '#666', marginBottom: 20},
  loginBtn: {backgroundColor: '#2E7D32', borderRadius: 10, paddingHorizontal: 32, paddingVertical: 12},
  loginBtnText: {color: '#FFF', fontWeight: '700', fontSize: 16},
  filterRow: {
    flexDirection: 'row',
    backgroundColor: '#FFFFFF',
    padding: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  filterTab: {
    flex: 1,
    paddingVertical: 8,
    alignItems: 'center',
    borderRadius: 8,
  },
  filterTabActive: {backgroundColor: '#2E7D32'},
  filterTabText: {fontSize: 14, color: '#666', fontWeight: '600'},
  filterTabTextActive: {color: '#FFFFFF'},
  item: {
    backgroundColor: '#FFFFFF',
    marginHorizontal: 12,
    marginTop: 10,
    borderRadius: 12,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.06,
    shadowRadius: 3,
    elevation: 2,
  },
  itemMain: {padding: 14},
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 4,
  },
  itemName: {
    flex: 1,
    fontSize: 15,
    fontWeight: '700',
    color: '#1A1A1A',
    marginRight: 8,
  },
  itemLocation: {fontSize: 13, color: '#666', marginBottom: 4},
  itemDate: {fontSize: 12, color: '#999'},
  itemActions: {
    flexDirection: 'row',
    borderTopWidth: 1,
    borderTopColor: '#F0F0F0',
  },
  statusBtn: {
    flex: 1,
    padding: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statusBtnTodo: {backgroundColor: '#F3F3F3'},
  statusBtnDone: {backgroundColor: '#E8F5E9'},
  statusBtnText: {fontSize: 13, fontWeight: '600', color: '#444'},
  removeBtn: {
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
    borderLeftWidth: 1,
    borderLeftColor: '#F0F0F0',
  },
  removeBtnText: {fontSize: 18},
  empty: {
    textAlign: 'center',
    padding: 32,
    color: '#999',
    fontSize: 15,
    fontStyle: 'italic',
  },
});

export default FavoritesScreen;
